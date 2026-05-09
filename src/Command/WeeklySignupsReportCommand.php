<?php

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\MailerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mime\Address;

#[AsCommand(
    name: 'app:admin:weekly-signups-report',
    description: 'Envoie aux administrateurs un rapport des inscriptions de la semaine.',
)]
class WeeklySignupsReportCommand extends Command
{
    public function __construct(
        private UserRepository $userRepository,
        private MailerService $mailerService,
        private string $reportEmail,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Fenêtre en jours (défaut : 7)', 7)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche le rapport sans envoyer d\'email');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $days = max(1, (int) $input->getOption('days'));
        $dryRun = (bool) $input->getOption('dry-run');

        $since = (new \DateTimeImmutable())->modify('-'.$days.' days');
        $newUsers = $this->userRepository->findCreatedSince($since);

        $io->title(sprintf('Rapport hebdomadaire — %d inscriptions sur les %d derniers jours', count($newUsers), $days));

        if (count($newUsers) > 0) {
            $rows = [];
            foreach ($newUsers as $u) {
                $rows[] = [
                    $u->getId(),
                    $u->getDisplayName(),
                    $u->getEmail(),
                    $u->isVerified() ? '✓' : '✗',
                    $u->getCreatedAt()?->format('d/m/Y H:i'),
                ];
            }
            $io->table(['ID', 'Nom', 'Email', 'Vérifié', 'Inscrit le'], $rows);
        } else {
            $io->note('Aucune nouvelle inscription sur la période.');
        }

        if ($dryRun) {
            $io->warning('Mode --dry-run : aucun email envoyé.');
            return Command::SUCCESS;
        }

        try {
            $this->mailerService->sendWeeklySignupsReport([new Address($this->reportEmail)], $newUsers, $since);
            $io->success(sprintf('Rapport envoyé à %s.', $this->reportEmail));
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Échec de l\'envoi : '.$e->getMessage());
            return Command::FAILURE;
        }
    }
}
