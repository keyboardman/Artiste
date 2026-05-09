<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:promote', description: 'Ajoute ou retire ROLE_ADMIN à un utilisateur.')]
class UserPromoteCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email de l\'utilisateur')
            ->addOption('revoke', null, InputOption::VALUE_NONE, 'Retire ROLE_ADMIN au lieu de l\'ajouter');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $user = $this->userRepository->findOneBy(['email' => $input->getArgument('email')]);
        if (!$user) {
            $io->error(sprintf('Aucun utilisateur trouvé avec l\'email "%s".', $input->getArgument('email')));
            return Command::FAILURE;
        }

        $roles = $user->getRoles();
        $revoke = $input->getOption('revoke');

        if ($revoke) {
            $roles = array_values(array_filter($roles, fn($r) => $r !== 'ROLE_ADMIN'));
            $user->setRoles($roles);
            $this->em->flush();
            $io->success(sprintf('ROLE_ADMIN retiré à %s.', $user->getEmail()));
        } else {
            if (!in_array('ROLE_ADMIN', $roles, true)) {
                $roles[] = 'ROLE_ADMIN';
                $user->setRoles($roles);
                $this->em->flush();
            }
            $io->success(sprintf('%s est maintenant administrateur.', $user->getEmail()));
        }

        return Command::SUCCESS;
    }
}
