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
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

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