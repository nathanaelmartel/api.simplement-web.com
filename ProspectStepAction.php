<?php

namespace App\Service;

use App\Entity\Prospect;
use App\Entity\ProspectDocument;
use App\Entity\ProspectDocumentType;
use App\Entity\ProspectSignataire;
use App\Entity\ProspectStatus;
use App\Entity\ProspectStep;
use App\Entity\ProspectStepDate;
use App\Entity\Restaurant;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use WiziYousignClient\WiziSignClient;

class ProspectStepAction
{
    public $em;
    public $project_dir;
    public $dropbox;
    public $setting;
    public $mail;
    public $history;
    private $contract;
    private $env;
    private $router;

    public function __construct(
        EntityManagerInterface $entityManager,
        KernelInterface $appKernel,
        Setting $setting,
        Mail $mail,
        HistoryService $history,
        Contract $contract,
        UrlGeneratorInterface $router
    ) {
        $this->em = $entityManager;
        $this->project_dir = $appKernel->getProjectDir();
        $this->local_path = $appKernel->getProjectDir().'/fichiers';
        $this->contract = $contract;
        $this->setting = $setting;
        $this->mail = $mail;
        $this->history = $history;
        $this->env = $appKernel->getEnvironment();
        $this->dropbox = new \League\Flysystem\Filesystem(
            new \Spatie\FlysystemDropbox\DropboxAdapter(
                new \Spatie\Dropbox\Client(
                    $setting->get('dropbox')
                )
            )
        );
        $this->router = $router;
    }

    public function configure(
        ProspectStep $step,
        Prospect $prospect,
        ?ProspectStepDate $prospect_step = null,
        array $post = [],
        ?User $user = null
    ) {
        if (is_null($prospect->getToken())) {
            $prospect->setToken($this->generatePassword(64, 3));
            $this->em->persist($prospect);
            $this->em->flush();
        }

        $this->prospect = $prospect;
        $this->step = $step;
        $this->user = $user;

        if (!$prospect_step) {
            $prospect_step = $prospect->getStep($step);
        }

        if (!$prospect_step) {
            $prospect_step = new ProspectStepDate();
            $prospect_step->setProspect($prospect);
            $prospect_step->setStep($step);
            $prospect_step->setDate(new \DateTime('now'));
        }
        $this->prospect_step = $prospect_step;

        $this->values = $this->extractPostValues($post);

        $action = isset($post['action']) ? $post['action'] : 'send_mail';
        $prospect_step->addData([$action => $this->values]);
        $this->em->persist($prospect_step);
        $this->em->flush();
    }

    public function generateContract($contract_slug, $regenerate = false)
    {
        $contract = $this->em->getRepository(\App\Entity\Contract::class)->findOneBy(['slug' => $contract_slug]);
        if (!$contract) {
            throw new \Exception(sprintf('Le modèle de contrat «%s» est manquant.', $contract_slug));
        }
        $document = null;
        $documents = $this->em->getRepository(ProspectDocument::class)->findBy([
            'prospect' => $this->prospect,
            'contract' => $contract,
        ]);
        foreach ($documents as $document) {
            if ($regenerate) {
                continue;
            }
            if (!is_null($document->getSignedAt())) {
                return true;
            }
            if (!is_null($document->getYousignMemberId()) && !is_null($document->getYousignProcedureId())) {
                return false;
            }
        }

        $this->contract->configureProspect($contract, $this->prospect);

        $document = $this->contract->saveProspect(null, $document);

        return true;
    }

    public function sendSignature($contract_slug, $regenerate = false)
    {
        $contract = $this->em->getRepository(\App\Entity\Contract::class)->findOneBy(['slug' => $contract_slug]);
        if (!$contract) {
            throw new \Exception(sprintf('Le modèle de contrat «%s» est manquant.', $contract_slug));
        }
        $document = null;
        $documents = $this->em->getRepository(ProspectDocument::class)->findBy([
            'prospect' => $this->prospect,
            'contract' => $contract,
        ]);
        foreach ($documents as $document) {
            if ($regenerate) {
                continue;
            }
            if (!is_null($document->getSignedAt())) {
                return true;
            }
            if (!is_null($document->getYousignMemberId()) && !is_null($document->getYousignProcedureId())) {
                return false;
            }
        }

        $this->contract->configureProspect($contract, $this->prospect);

        $document = $this->contract->saveProspect(null, $document);

        return $this->sendDocumentToYousign($document);
    }

    /*
            Pour le DIP :
            - je génère le modèle de contrat «DIP» : https://gestion.lunicco.fr/admin/contract/17
            - je met avant le contenu du fichier /Documents-Prospect/DIP/DIP_PRE.pdf (cf dropbox)
            - je met après le contenu du fichier /Documents-Prospect/DIP/DIP_POST.pdf
            - je met ensuite les annexes du prospect (dans le dossier «03. Annexe» du prospect, en gros ceux uploadé au étape précédente)
            - je met ensuite tous les fichier contenu dans le dossier /Documents-Prospect/DIP/ANNEXES dans l'ordre alphabétique
            - je fait un seul pdf avec tout ça, que j'enregistre dans le de dossier «02. Contrat» du prospect.
            - je l'envoie à yousign
            - j'enoie le lien pour la signature au prospect
      */
    public function sendDIP()
    {
        $contract = $this->em->getRepository(\App\Entity\Contract::class)->findOneBy(['slug' => 'dip']);
        if (!$contract) {
            throw new \Exception('Le modèle de contrat «DIP» est manquant.');
        }
        $document = null;
        $documents = $this->em->getRepository(ProspectDocument::class)->findBy([
            'prospect' => $this->prospect,
            'contract' => $contract,
        ]);
        foreach ($documents as $document) {
            if (!is_null($document->getSignedAt())) {
                return true;
            }
            if (!is_null($document->getYousignMemberId()) && !is_null($document->getYousignProcedureId())) {
                return false;
            }
        }

        $this->contract->configureProspect($contract, $this->prospect);
        $document = $this->contract->saveProspect(null, $document);

        $files = [
            '/Documents-Prospect/DIP/DIP_PRE.pdf',
            $document->getDropboxPath(),
            '/Documents-Prospect/DIP/DIP_POST.pdf',
        ];
        foreach ($this->prospect->getDocumentsAnnexeDIP() as $doc) {
            $files[] = $doc->getDropboxPath();
        }
        foreach ($this->dropbox->listContents('/Documents-Prospect/DIP/ANNEXES') as $file) {
            if (0 == substr_count($file['basename'], '.pdf')) {
                continue;
            }
            $files[] = '/'.$file['path'];
        }
        foreach ($files as $file) {
            if (!file_exists(dirname($this->local_path.$file))) {
                mkdir(dirname($this->local_path.$file), 0777, true);
            }
            file_put_contents(
                $this->local_path.$file,
                $this->dropbox->read($file)
            );
        }

        $pdf = new \Clegginabox\PDFMerger\PDFMerger();
        foreach ($files as $file) {
            $pdf->addPDF($this->local_path.$file, 'all');
        }
        $pdf->merge('file', $this->local_path.$document->getDropboxPath());
        $this->dropbox->put(
            $document->getDropboxPath(),
            file_get_contents($this->local_path.$document->getDropboxPath())
        );
        $document->setSize(filesize($this->local_path.$document->getDropboxPath()));
        $this->em->persist($document);
        $this->em->flush();

        return $this->sendDocumentToYousign($document);
    }

    public function uploadFile(array $files)
    {
        $step_data = $this->step->getData(true);

        foreach ($files as $key => $file) {
            $type = $this->em->getRepository(ProspectDocumentType::class)->findOneBy(['code' => $step_data['actions']['upload_file'][$key]['type_code']]);
            $filename = sprintf(
                '%s/%s/%s',
                $this->prospect->getDropboxFolder(),
                $type->getFolder(),
                $step_data['actions']['upload_file'][$key]['filename']
            );
            $filename = str_replace('.pdf', date('_Y-m-d_H-i-s').'.pdf', $filename);

            if (!file_exists(pathinfo($this->local_path.$filename, PATHINFO_DIRNAME))) {
                mkdir(pathinfo($this->local_path.$filename, PATHINFO_DIRNAME), 0777, true);
            }
            file_put_contents($this->local_path.$filename, file_get_contents($file['tmp_name']));
            $this->dropbox->write($filename, file_get_contents($file['tmp_name']));

            $prospect_document = new ProspectDocument();
            $prospect_document->setType($type);
            $prospect_document->setProspect($this->prospect);
            $prospect_document->setFilename(basename($filename));
            $prospect_document->setSize($file['size']);

            $this->em->persist($prospect_document);
            $this->em->flush();
        }

        return true;
    }

    public function sendMail()
    {
        if (is_null($this->step->getMail())) {
            return false;
        }

        $step_data = $this->step->getData(true);

        $vars = [
            'prospectnom' => $this->prospect->getFirstname(),
            'usernom' => ($this->user) ? $this->user->getFullname() : 'L\'équipe Lunicco',
            'usertel' => ($this->user) ? $this->user->getPhoneLink() : '',
            'urlficheprojet' => sprintf('[ROOTURL]prospect-franchise/%s/%s/fiche-projet', $this->prospect->getSlug(), $this->prospect->getToken()),
            'urlrapportimmersion' => sprintf('[ROOTURL]prospect-franchise/%s/%s/rapport-immersion', $this->prospect->getSlug(), $this->prospect->getToken()),
        ];
        foreach ($this->values as $key => $value) {
            if ((is_object($value)) && ('DateTime' == get_class($value))) {
                $vars[$key] = $value->format('d/m/Y');
            } else {
                $vars[$key] = nl2br($value);
            }
            if ((isset($step_data['actions']['send_mail'][$key])) && ('restaurant' == $step_data['actions']['send_mail'][$key]['type'])) {
                $restaurant = $this->em->getRepository(Restaurant::class)->findOneBy(['id' => $value]);
                if ($restaurant) {
                    $vars['restaurantname'] = $restaurant->getName();
                    $vars['restaurantville'] = $restaurant->getVilleName();
                    $vars['restaurantadresse'] = $restaurant->getFullAddress();
                }
            }
        }

        $files = [];
        foreach ($this->step->getFiles() as $file) {
            if ($this->dropbox->has($file)) {
                $path = $this->project_dir.'/fichiers/'.$file;
                if (!file_exists(dirname($path))) {
                    mkdir(dirname($path), 0777, true);
                }
                if (!file_exists($path)) {
                    file_put_contents($path, $this->dropbox->read($file));
                }
                $files[] = [
                    'path' => $path,
                    'filename' => basename($file),
                    'file_size' => filesize($path),
                ];
            }
        }

        if (isset($vars['mail_subject'])) {
            unset($vars['mail_subject']);
        }
        if (isset($vars['mail_body'])) {
            unset($vars['mail_body']);
        }
        $mail = [
            'from' => ($this->user) ? $this->user->getEmail('swift') : ['prospect@lunicco.fr' => 'Lunicco'],
            'to' => $this->prospect->getMail('swift'),
            'template' => $this->step->getMail()->getSlug(),
            'var' => $vars,
            'file_attachments' => $files,
        ];
        if (isset($this->values['mail_subject'])) {
            $mail['subject'] = $this->values['mail_subject'];
        }
        if (isset($this->values['mail_body'])) {
            $mail['content'] = $this->values['mail_body'];
        }

        return $this->mail->send($mail, $this->user, $this->prospect);
    }

    public function postAction($action = '')
    {
        $step_data = $this->step->getData(true);

        if (isset($step_data['actions'][$action]['status'])) {
            $this->changeStatus($step_data['actions'][$action]['status']);
        } elseif (isset($step_data['status'])) {
            $this->changeStatus($step_data['status']);
        }

        if (isset($step_data['next_step_condition'])) {
            if ('true' == $step_data['next_step_condition']) {
                $this->nextStep();
            } elseif (method_exists($this->prospect, $step_data['next_step_condition'])) {
                if ($this->prospect->{$step_data['next_step_condition']}()) {
                    $this->nextStep();
                }
            }
        }

        if (isset($step_data['set_next_rdv'])) {
            $this->setNextRdv($this->values);
        }
    }

    public function changeStatus($status_id = null)
    {
        $status = $this->em->getRepository(ProspectStatus::class)->findOneBy(['id' => $status_id]);
        if (!$status) {
            return false;
        }

        if ($status->getId() == $this->prospect->getStatus()->getId()) {
            return false;
        }

        $this->prospect->setStatus($status);
        $this->em->persist($this->prospect);
        $this->em->flush();

        $this->history->add(
            'Changement de statut',
            $this->prospect,
            $status->getBadge(),
            [],
            $this->user,
        );

        $this->prospect_step->addData(['status' => $status->getId()]);
        $this->em->persist($this->prospect_step);
        $this->em->flush();

        return true;
    }

    public function nextStep()
    {
        $next_step = $this->em->getRepository(ProspectStep::class)->findOneBy(['sort_key' => $this->step->getSortKey() + 1]);
        if (!$next_step) {
            return false;
        }

        $this->prospect->setCurrentStep($next_step);
        $this->em->persist($this->prospect);
        $this->em->flush();

        $this->history->add(
            'Changement d\'étape',
            $this->prospect,
            sprintf('%s %s', $this->step->getBadge(), $this->step->getName()),
            [],
            $this->user
        );

        return true;
    }

    private function setNextRdv($values)
    {
        $step_data = $this->step->getData(true);
        $date = null;

        if (isset($step_data['set_next_rdv']['date'], $step_data['set_next_rdv']['time'])) {
            $date = \DateTime::createFromFormat('d/m/Y H:i', $values[$step_data['set_next_rdv']['date']].' '.$values[$step_data['set_next_rdv']['time']]);
        } elseif (isset($step_data['set_next_rdv']['date'])) {
            $date = \DateTime::createFromFormat('d/m/Y', $values[$step_data['set_next_rdv']['date']]);
        }

        if (is_null($date)) {
            return false;
        }
        if (!isset($step_data['set_next_rdv']['type'])) {
            return false;
        }

        $this->prospect->setNextRdvAt($date);
        $this->prospect->setNextRdvType($step_data['set_next_rdv']['type']);
        $this->em->persist($this->prospect);
        $this->em->flush();

        $history = $this->history->add(
            'RDV',
            $this->prospect,
            $this->prospect->getNextRdvTypeLabel(),
            $values,
            $this->user,
        );
        $history->setCreated($date);
        $history->setUpdated($date);
        $this->em->persist($history);
        $this->em->flush();

        return true;
    }

    private function sendYousignLink(ProspectDocument $document, array $response = [])
    {
        if ($this->mail->send([
            'to' => $this->prospect->getEmail('swift'),
            'var' => [
                'PROSPECTNOM' => $this->prospect->getFirstname(),
                'NOMDOCUMENT' => $document->getContract()->getName(),
                'URLDOCUMENT' => $document->getYousignSignLink($this->setting->get('yousign_web_url_'.$this->env)),
            ],
            'template' => 'signature-prospect',
        ], null, $document)) {
            $this->history->add(
                '[Yousign] Demande de signature électronique',
                $document,
                '',
                $response,
            );

            return true;
        }

        return false;
    }

    private function sendDocumentToYousign(ProspectDocument $document)
    {
        $this->em->getRepository(ProspectSignataire::class)->checkAtLeatOneSignataire($this->prospect);
        $this->em->refresh($this->prospect);

        $client = new WiziSignClient(
            $this->setting->get('yousign_api_key_'.$this->env),
            $this->env
        );
        $parameters = [
            'name' => sprintf('signature de %s', $document->getContract()->getName()),
            'description' => sprintf('signature de %s', $document->getContract()->getName()),
            'start' => false,
        ];
        $client->AdvancedProcedureCreate(
            $parameters,
            true,
            'POST',
            $this->router->generate('yousign', [], UrlGenerator::ABSOLUTE_URL),
            'testwebhook'
        );
        $client->AdvancedProcedureAddFile(
            $this->local_path.$document->getDropboxPath(),
            $document->getFilename()
        );
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($this->local_path.$document->getDropboxPath());

        foreach ($this->prospect->getSignataires() as $signataire) {
            $prospect_phone = $signataire->getPhone();

            try {
                $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
                $numberProto = $phoneUtil->parse($prospect_phone, 'FR');

                if ($phoneUtil->isValidNumber($numberProto)) {
                    $prospect_phone = $phoneUtil->format($numberProto, \libphonenumber\PhoneNumberFormat::E164);
                }
            } catch (\libphonenumber\NumberParseException $e) {
            }
            $client->AdvancedProcedureAddMember(
                $signataire->getFirstname(),
                $signataire->getLastname(),
                $signataire->getMail(),
                $prospect_phone
            );
            $client->AdvancedProcedureFileObject(
                '320,111,462,197', // https://placeit.yousign.fr/
                count($pdf->getPages()),
                'Lu et approuvé',
                sprintf('Signé par %s', $signataire->getFullname()),
                sprintf('Signé par %s', $signataire->getFullname())
            );
        }
        $response = $client->AdvancedProcedurePut();

        $response = json_decode($response, true);

        $document->setSignedAt(null);
        $document->setYousignProcedureId($client->getIdProcedure());
        $document->setYousignMemberId($response['members'][0]['id']);
        $this->em->persist($document);
        $this->em->flush();
        $this->em->refresh($document);

        return $this->sendYousignLink($document, $response);
    }

    private function extractPostValues(array $post)
    {
        $values = [
            'posted_at' => new \DateTime('now'),
        ];

        $step_data = $this->step->getData(true);
        $action = isset($post['action']) ? $post['action'] : 'send_mail';

        foreach ($post as $key => $value) {
            $values[$key] = $value;
            if (!isset($step_data['actions'][$action][$key])) {
                continue;
            }
            if ('restaurant' == $step_data['actions'][$action][$key]['type']) {
                $restaurant = $this->em->getRepository(Restaurant::class)->findOneBy(['id' => $value]);
                if ($restaurant) {
                    $values['restaurantname'] = $restaurant->getName();
                    $values['restaurantvelle'] = $restaurant->getVilleName();
                }
            }
            if ('date' == $step_data['actions'][$action][$key]['type']) {
                $value = new \DateTime($value);
                $values[$key] = $value->format('d/m/Y');
            }

            $setters = [
                'territoire' => 'setTerritoire',
                'secteurGeographique' => 'setSecteurGeographique',
                'droitReservation' => 'setDroitDeReservation',
                'formJuridique' => 'setCompanyFormJuridique',
                'company' => 'setCompanyName',
                'repartitionCapital' => 'setRepartitionCapital',
            ];
            if (isset($setters[$key])) {
                $this->prospect->{$setters[$key]}($value);
            }
        }

        $this->em->persist($this->prospect);
        $this->em->flush();

        return $values;
    }

    private function generatePassword($length = 9, $strength = 0)
    {
        $vowels = 'aeuy';
        $consonants = 'bdghjmnpqrstvz';
        if ($strength > 0) {
            $consonants .= 'BDGHJLMNPQRSTVWXZ';
            $vowels .= 'AEUY';
        }
        if ($strength > 1) {
            $consonants .= '23456789';
        }/*
        if ($strength > 2) {
            $consonants .= '@#$%';
        }*/

        $password = '';
        $alt = time() % 2;
        for ($i = 0; $i < $length; ++$i) {
            if (1 == $alt) {
                $password .= $consonants[(rand() % strlen($consonants))];
                $alt = 0;
            } else {
                $password .= $vowels[(rand() % strlen($vowels))];
                $alt = 1;
            }
        }

        return $password;
    }
}
