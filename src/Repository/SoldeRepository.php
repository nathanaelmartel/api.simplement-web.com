<?php

namespace App\Repository;

use App\Entity\Solde;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Solde|null find($id, $lockMode = null, $lockVersion = null)
 * @method Solde|null findOneBy(array $criteria, array $orderBy = null)
 * @method Solde[]    findAll()
 * @method Solde[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SoldeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Solde::class);
    }

    // /**
    //  * @return Solde[] Returns an array of Solde objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Solde
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
