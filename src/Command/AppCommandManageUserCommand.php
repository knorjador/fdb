<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;

#[AsCommand(
    name: 'user',
    description: 'Manage users',
)]
class AppCommandManageUserCommand extends Command
{

    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::OPTIONAL, 'Email of the user')
            ->addOption('action', null, InputOption::VALUE_REQUIRED, 'Action to perform (add or remove)', 'add')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        $action = $input->getOption('action');

        if ($action === 'add') {
            if (!$email) {
                $output->writeln('Email is required for adding a user');
                return Command::FAILURE;
            }

            $this->addUser($email);
        } elseif ($action === 'remove') {
            if (!$email) {
                $output->writeln('Email is required for removing a user');
                return Command::FAILURE;
            }

            $this->removeUser($email, $output);
        } elseif ($action === 'list') {
            $this->listUsers($output);
        } else {
            $output->writeln('Invalid action. Use "add" or "remove"');
            return Command::FAILURE;
        }

        if ($action !== 'list') {
            $verb = $action === 'add' ? 'added' : 'removed';
            $io->success(sprintf('User "%s" well %s', $email, $verb));
        }

        return Command::SUCCESS;
    }

    private function addUser(string $email): void
    {
        $user = new User();
        $user->setEmail($email);
        $user->setCheckAuth($this->generateHexAuthToken());
        $user->setRoles(['ROLE_USER']);

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    private function removeUser(string $email, OutputInterface $output): void
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            $output->writeln('User not found.');
            return;
        }

        $this->entityManager->remove($user);
        $this->entityManager->flush();
    }

    private function listUsers(OutputInterface $output): void
    {
        $users = $this->entityManager->getRepository(User::class)->findAll();

        if (empty($users)) {
            $output->writeln('No users found.');
            return;
        }

        $output->writeln('Users:');
        foreach ($users as $user) {
            $output->writeln('- ' . $user->getEmail() . ' ' . $user->getCheckAuth());
        }
    }

    private function generateHexAuthToken(int $length = 32): string
    {
        return bin2hex(openssl_random_pseudo_bytes($length));
    }

}
