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

    /**
     * Constructor CompanyRepository
     *
     * @param ManagerRegistry $registry The ManagerRegistry instance
    */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Company::class);
    }

    /**
     * Save a new company associated with a user
     *
     * @param User $user
     * @param array $data
     * @return Company|bool The saved company or false on failure
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
     * Check if there is a company with the given SIRET associated with the given user
     *
     * @param User $user
     * @param string $siret
     * @return Company|bool The found company or false on failure
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
     * Update a company associated with a user
     *
     * @param User $user
     * @param array $data
     * @return array An array containing the update status and the updated company
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
     * Delete a company associated with a user by SIRET
     *
     * @param User $user
     * @param string $siret
     * @return Company|bool The deleted company or false on failure
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