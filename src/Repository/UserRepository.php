<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
*/
class UserRepository extends ServiceEntityRepository
{

    /**
     * Constructor UserRepository
     *
     * @param ManagerRegistry $registry The ManagerRegistry instance
    */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Finds a user by email
     *
     * @param string $email
     * @return User|null The user found, or null if no user was found
    */
    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * Finds the companies associated with a user
     *
     * @param User
     * @return array The array of companies associated with the user
    */
    public function findUserCompanies(User $user): array
    {
        try {
            $userCompanies = $user->getCompanies();
            $companies = [];

            foreach ($userCompanies as $company) {
                $companies[] = [
                    'siret' => $company->getSiret(),
                    'name' => $company->getName(),
                    'address' => $company->getAddress(),
                    'siren' => $company->getSiren(),
                    'tva' => $company->getTva()
                ];
            }

            return $companies;
        } catch (\Exception $e) {
            // deal with it
  
            return [];
        }
    }

}