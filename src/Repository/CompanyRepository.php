<?php

namespace App\Repository;

use Psr\Log\LoggerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use App\Entity\User;
use App\Entity\Company;

/**
 * @extends ServiceEntityRepository<Company>
*/
class CompanyRepository extends ServiceEntityRepository
{
    
    private LoggerInterface $logger;

    /**
     * CompanyRepository constructor
     *
     * @param ManagerRegistry $registry The ManagerRegistry instance
    */
    public function __construct(ManagerRegistry $registry, LoggerInterface $logger)
    {
        parent::__construct($registry, Company::class);
        $this->logger = $logger;
    }

    /**
     * Persists and flushes a Company entity
     *
     * @param Company $company The company entity to save
     * @return bool True if the company was saved successfully, false otherwise
    */
    public function save(User $user, array $data): Company|bool
    {
        $entityManager = $this->getEntityManager();

        try {
            $company = new Company();
            $company
                ->setSiret($data['siret'])
                ->setName($data['name'])
                ->setAddress($data['address'])
                ->setSiren($data['siren'])
                ->setTva($data['tva'])
                ->addUser($user);

            $entityManager->persist($company);
            $entityManager->flush();

            return $company;
        } catch (\Exception $e) {
            // deal with it

            return false;
        }
    }

    /**
     * Check if there is a company with the given SIRET associated with the given User
     *
     * @param User $user
     * @param string $siret
     * @return bool
    */
    public function existsCompanyForUser(User $user, string $siret): Company|bool
    {
        try {
            $company = $this->createQueryBuilder('c')
                ->join('c.user', 'u')
                ->where('c.siret = :siret')
                ->andWhere('u.id = :userId')
                ->setParameter('siret', $siret)
                ->setParameter('userId', $user->getId())
                ->getQuery()
                ->getOneOrNullResult();

            if ($company) {
                return $company;
            } 

            return false;
        } catch (\Exception $e) {
            // deal with it

            return false;
        }
    }

    /**
     * Update a Company entity associated with the given User
     *
     * @param User $user The user who owns the company
     * @param array $data The data to update the company
     * @return Company|bool Company if the company was updated successfully, false otherwise
    */
    public function update(User $user, array $data): array
    {
        $entityManager = $this->getEntityManager();
        $entityManager->beginTransaction();
        $updated = ['fail' => true, 'modified' => false, 'company' => []];

        try {
            $company = $this->createQueryBuilder('c')
                ->join('c.user', 'u')
                ->where('c.siret = :siret')
                ->andWhere('u.id = :user_id')
                ->setParameter('siret', $data['siret'])
                ->setParameter('user_id', $user->getId())
                ->getQuery()
                ->getOneOrNullResult();

            if ($company) {
                $updated['fail'] = false;

                if (
                    $data['name'] !== $company->getName() ||
                    $data['address'] !== $company->getAddress() ||
                    $data['tva'] !== $company->getTva()
                ) {
                    $company
                        ->setName($data['name'])
                        ->setAddress($data['address'])
                        ->setTva($data['tva']);

                    $entityManager->flush();
                    $entityManager->commit();

                    $updated['modified'] = true;
                    $updated['company'] = $company;
                }
            }
    
            return $updated;
        } catch (\Exception $e) {
            $entityManager->rollback();
            
            // deal with it 

            return $updated;
        }
    }

    /**
     * Deletes a Company entity by its SIRET and associated User
     *
     * @param string $siret The SIRET number of the company
     * @param User $user The user who owns the company
     * @return bool True if the company was deleted, false otherwise
    */
    public function deleteCompanyBySiret(User $user, string $siret): Company|bool
    {
        $entityManager = $this->getEntityManager();
        $entityManager->beginTransaction();

        try {
            $company = $this->createQueryBuilder('c')
                ->join('c.user', 'u')
                ->where('c.siret = :siret')
                ->andWhere('u.id = :user_id')
                ->setParameter('siret', $siret)
                ->setParameter('user_id', $user->getId())
                ->getQuery()
                ->getOneOrNullResult();

            if ($company) {
                $entityManager->remove($company);
                $entityManager->flush();
                $entityManager->commit();

                return $company;
            } else {
                $entityManager->rollback();

                return false;
            }
        } catch (\Exception $e) {
            $entityManager->rollback();
    
            // deal with it

            return false;
        }
    }

}