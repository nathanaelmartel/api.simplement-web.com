<?php

namespace App\Command;

use App\Entity\Departement;
use App\Entity\Region;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:fetch:departement',
    description: 'Récupère les départements depuis https://geo.api.gouv.fr/decoupage-administratif/departements',
)]
class FetchDepartementCommand extends Command
{
    public function __construct(
        EntityManagerInterface $em
    ) {
        parent::__construct();

        $this->em = $em;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
            ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!file_exists('documentation/data.gouv.fr')) {
            mkdir('documentation/data.gouv.fr', 0777, true);
        }

        $url = 'https://geo.api.gouv.fr/regions';
        $io->writeln(sprintf('téléchargement depuis : %s', $url));
        $regions_officiels = json_decode(file_get_contents($url), true);
        file_put_contents('documentation/data.gouv.fr/regions.json', json_encode($regions_officiels, JSON_PRETTY_PRINT));
        $regions = [];
        foreach ($regions_officiels as $r) {
            $R = $this->em->getRepository(Region::class)->findOneBy(['code' => $r['code']]);
            if (!$R) {
                $R = new Region();
                $R->setCode($r['code']);
            }
            $R->setName($r['nom']);
            $this->em->persist($R);
            $this->em->flush();
            $regions[$r['code']] = $R;
        }

        $url = 'https://geo.api.gouv.fr/departements';
        $io->writeln(sprintf('téléchargement depuis : %s', $url));
        $departements = json_decode(file_get_contents($url), true);
        file_put_contents('documentation/data.gouv.fr/departements.json', json_encode($departements, JSON_PRETTY_PRINT));
        $nb_new = 0;
        foreach ($departements as $d) {
            $departement = $this->em->getRepository(Departement::class)->findOneBy([
                'code' => $d['code'],
            ]);

            if (!$departement) {
                $departement = new Departement();
                $departement->setCode($d['code']);
                ++$nb_new;
            }
            $departement->setName($d['nom']);
            if (isset($regions[$d['codeRegion']])) {
                $departement->setRegion($regions[$d['codeRegion']]);
            }

            $this->em->persist($departement);
            $this->em->flush();
        }

        $others_departements = [
            '20' => 'Corse',
            '975' => 'Saint-Pierre-et-Miquelon',
            '977' => 'Saint-Barthélemy',
            '978' => 'Saint-Martin',
        ];

        foreach ($others_departements as $code => $name) {
            $departement = $this->em->getRepository(Departement::class)->findOneBy([
                'code' => $code,
            ]);

            if (!$departement) {
                $departement = new Departement();
                $departement->setCode($code);
                ++$nb_new;
            }
            $departement->setName($name);

            $this->em->persist($departement);
            $this->em->flush();
        }

        $io->success(sprintf('%s nouveaux départements (%s au total)', $nb_new, count($departements)));

        return Command::SUCCESS;
    }
}
