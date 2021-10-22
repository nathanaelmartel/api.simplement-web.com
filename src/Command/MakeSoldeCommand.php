<?php

namespace App\Command;

use App\Entity\Departement;
use App\Entity\Solde;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:make:solde',
    description: 'Make solde in database for current year and next year with https://www.legifrance.gouv.fr/loda/id/LEGITEXT000038524717/',
)]
class MakeSoldeCommand extends Command
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

        $years = [
            (int) date('Y'),
            (int) date('Y') + 1,
        ];
        $periods = [
            'Hiver',
            'Été',
        ];
        $departements = $this->em->getRepository(Departement::class)->findBy([], ['code' => 'ASC']);

        foreach ($years as $year) {
            foreach ($periods as $period) {
                $start_at = $this->getStartAt($year, $period);
                $end_at = $this->getEndAt($start_at);

                $io->writeln(sprintf('%s %s du %s au %s', $period, $year, $start_at->format('d/m/Y'), $end_at->format('d/m/Y')));

                foreach ($departements as $departement) {
                    $solde = $this->em->getRepository(Solde::class)->findOneBy([
                        'name' => $period,
                        'annee' => $year,
                        'departement' => $departement,
                    ]);
                    if (!$solde) {
                        $solde = new Solde();
                        $solde->setName($period);
                        $solde->setAnnee($year);
                        $solde->setDepartement($departement);
                    }

                    $solde->setStartAt($start_at);
                    $solde->setEndAt($end_at);

                    $this->em->persist($solde);
                    $this->em->flush();
                }
            }
        }

        $period_exceptions = [
            'Hiver' => ['54', '55', '57', '66', '88', '971', '974', '975', '977', '978'],
            'Été' => ['06', '2A', '2B', '971', '972', '974', '975', '977', '978'],
        ];

        foreach ($years as $year) {
            foreach ($periods as $period) {
                $departements = $this->em->getRepository(Departement::class)->findBy(['code' => $period_exceptions[$period]], ['code' => 'ASC']);
                foreach ($departements as $departement) {
                    $solde = $this->em->getRepository(Solde::class)->findOneBy([
                        'name' => $period,
                        'annee' => $year,
                        'departement' => $departement,
                    ]);
                    if (!$solde) {
                        $solde = new Solde();
                        $solde->setName($period);
                        $solde->setAnnee($year);
                        $solde->setDepartement($departement);
                    }

                    $start_at = $this->getStartAtException($year, $period, $departement->getCode());
                    $end_at = $this->getEndAt($start_at);
                    $io->writeln(sprintf(
                        '%s (%s) %s %s du %s au %s',
                        $departement->getName(),
                        $departement->getCode(),
                        $period,
                        $year,
                        $start_at->format('d/m/Y'),
                        $end_at->format('d/m/Y')
                    ));

                    $solde->setStartAt($start_at);
                    $solde->setEndAt($end_at);

                    $this->em->persist($solde);
                    $this->em->flush();
                }
            }
        }

        return Command::SUCCESS;
    }

    private function getStartAtException($year, $period, $departement)
    {
        if ((in_array($departement, ['06', '66'])) && ('Été' == $period)) {
            // premier mercredi du mois de juillet
            $start_at = new \DateTime($year.'-07-01');
            $start_at->modify('next wednesday');

            return new \DateTime($start_at->format('Y-m-d 08:00:00'));
        }

        if ((in_array($departement, ['2A', '2B'])) && ('Été' == $period)) {
            // deuxième mercredi du mois de juillet
            $start_at = new \DateTime($year.'-07-01');
            $start_at->modify('next wednesday');
            $start_at->modify('next wednesday');

            return new \DateTime($start_at->format('Y-m-d 08:00:00'));
        }

        if ((in_array($departement, ['54', '55', '57', '88'])) && ('Hiver' == $period)) {
            // premier jour ouvré du mois de janvier
            $start_at = new \DateTime($year.'-01-02');
            if ((int) $start_at->format('N') > 5) {
                $start_at->modify('next monday');
            }

            return new \DateTime($start_at->format('Y-m-d 08:00:00'));
        }

        if ((in_array($departement, ['971'])) && ('Hiver' == $period)) {
            // premier samedi de janvier
            $start_at = new \DateTime($year.'-01-01');
            $start_at->modify('next saturday');

            return new \DateTime($start_at->format('Y-m-d 08:00:00'));
        }

        if ((in_array($departement, ['971'])) && ('Été' == $period)) {
            // dernier samedi de septembre
            $start_at = new \DateTime($year.'-10-01');
            $start_at->modify('last saturday');

            return new \DateTime($start_at->format('Y-m-d 08:00:00'));
        }

        if ((in_array($departement, ['972'])) && ('Été' == $period)) {
            // premier jeudi d'octobre
            $start_at = new \DateTime($year.'-09-30');
            $start_at->modify('next thursday');

            return new \DateTime($start_at->format('Y-m-d 08:00:00'));
        }

        if ((in_array($departement, ['974'])) && ('Hiver' == $period)) {
            // premier samedi du mois de septembre
            $start_at = new \DateTime($year.'-08-31');
            $start_at->modify('next saturday');

            return new \DateTime($start_at->format('Y-m-d 08:00:00'));
        }

        if ((in_array($departement, ['974'])) && ('Été' == $period)) {
            // premier samedi du mois de février
            $start_at = new \DateTime($year.'-01-31');
            $start_at->modify('next saturday');

            return new \DateTime($start_at->format('Y-m-d 08:00:00'));
        }

        if ((in_array($departement, ['975'])) && ('Été' == $period)) {
            // premier mercredi après le 14 juillet
            $start_at = new \DateTime($year.'-07-14');
            $start_at->modify('next wednesday');

            return new \DateTime($start_at->format('Y-m-d 08:00:00'));
        }

        if ((in_array($departement, ['975'])) && ('Hiver' == $period)) {
            // premier mercredi après le 15 janvier
            $start_at = new \DateTime($year.'-01-15');
            $start_at->modify('next wednesday');

            return new \DateTime($start_at->format('Y-m-d 08:00:00'));
        }

        if ((in_array($departement, ['977', '978'])) && ('Été' == $period)) {
            // premier samedi de mai
            $start_at = new \DateTime($year.'-04-30');
            $start_at->modify('next saturday');

            return new \DateTime($start_at->format('Y-m-d 08:00:00'));
        }

        if ((in_array($departement, ['977', '978'])) && ('Hiver' == $period)) {
            // deuxième samedi d'octobre
            $start_at = new \DateTime($year.'-09-30');
            $start_at->modify('next saturday');
            $start_at->modify('next saturday');

            return new \DateTime($start_at->format('Y-m-d 08:00:00'));
        }

        return $this->getStartAt($year, $period);
    }

    private function getStartAt($year, $period)
    {
        if ('Hiver' == $period) {
            /* les soldes d'hiver débutent le deuxième mercredi du mois de janvier à 8 heures du matin.
            Cette date est avancée au premier mercredi du mois de janvier lorsque le deuxième mercredi intervient après le 12 du mois */
            $start_at = new \DateTime($year.'-01-01');
            $start_at->modify('next wednesday');
            $start_at->modify('next wednesday');
            if ($start_at->format('d') > 12) {
                $start_at->modify('last wednesday');
            }

            return new \DateTime($start_at->format('Y-m-d 08:00:00'));
        }

        /* les soldes d'été débutent le dernier mercredi du mois de juin à 8 heures du matin.
        Cette date est avancée à l'avant-dernier mercredi du mois de juin lorsque le dernier mercredi intervient après le 28 du mois. */
        $start_at = new \DateTime($year.'-06-30 08:00:00');
        $start_at->modify('last wednesday');
        if ($start_at->format('d') > 28) {
            $start_at->modify('last wednesday');
        }

        return new \DateTime($start_at->format('Y-m-d 08:00:00'));
    }

    private function getEndAt(\DateTime $start_at)
    {
        $end_at = new \DateTime($start_at->format('Y-m-d 23:59:59'));
        $end_at = $end_at->modify('+4 weeks');

        return $end_at->modify('-1 day');
    }
}
