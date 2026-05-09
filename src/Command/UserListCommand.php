<?php

namespace App\Command;

use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:user:list', description: 'Liste les utilisateurs en base.')]
class UserListCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('admins', null, InputOption::VALUE_NONE, 'Affiche uniquement les administrateurs')
            ->addOption('unverified', null, InputOption::VALUE_NONE, 'Affiche uniquement les comptes non vérifiés');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $users = $this->userRepository->findAll();

        if ($input->getOption('admins')) {
            $users = array_filter($users, fn($u) => in_array('ROLE_ADMIN', $u->getRoles(), true));
        } elseif ($input->getOption('unverified')) {
            $users = array_filter($users, fn($u) => !$u->isVerified());
        }

        if (empty($users)) {
            $io->note('Aucun utilisateur trouvé.');
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($users as $user) {
            $roles = array_filter($user->getRoles(), fn($r) => $r !== 'ROLE_USER');
            $rows[] = [
                $user->getId(),
                $user->getEmail(),
                $user->getDisplayName(),
                implode(', ', $roles) ?: '-',
                $user->isVerified() ? '✓' : '✗',
                $user->getCreatedAt()?->format('d/m/Y H:i') ?? '-',
            ];
        }

        $io->table(['ID', 'Email', 'Nom', 'Rôles', 'Vérifié', 'Inscrit le'], $rows);
        $io->note(sprintf('%d utilisateur(s) affiché(s).', count($rows)));

        return Command::SUCCESS;
    }
}
