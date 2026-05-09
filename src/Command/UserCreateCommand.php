<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:user:create', description: 'Crée un utilisateur directement depuis la CLI.')]
class UserCreateCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email')
            ->addArgument('password', InputArgument::REQUIRED, 'Mot de passe en clair')
            ->addOption('firstname', null, InputOption::VALUE_REQUIRED, 'Prénom', 'Test')
            ->addOption('lastname', null, InputOption::VALUE_REQUIRED, 'Nom', 'User')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Donne ROLE_ADMIN')
            ->addOption('verified', null, InputOption::VALUE_NONE, 'Marque le compte comme vérifié');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getArgument('email');

        if ($this->userRepository->findOneBy(['email' => $email])) {
            $io->error(sprintf('Un utilisateur avec l\'email "%s" existe déjà.', $email));
            return Command::FAILURE;
        }

        $user = new User();
        $user->setEmail($email);
        $user->setFirstname($input->getOption('firstname'));
        $user->setLastname($input->getOption('lastname'));
        $user->setPassword($this->passwordHasher->hashPassword($user, $input->getArgument('password')));
        $user->setIsVerified($input->getOption('verified'));

        $roles = [];
        if ($input->getOption('admin')) {
            $roles[] = 'ROLE_ADMIN';
        }
        $user->setRoles($roles);

        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf(
            'Utilisateur créé : %s (ID %d)%s%s',
            $user->getEmail(),
            $user->getId(),
            $input->getOption('admin') ? ' [ADMIN]' : '',
            $input->getOption('verified') ? ' [VÉRIFIÉ]' : '',
        ));

        return Command::SUCCESS;
    }
}
