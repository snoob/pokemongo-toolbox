<?php

declare(strict_types=1);

namespace App\UI\Cli\Command;

use App\Application\Stardust\StardustInfo\StardustInfoHandler;
use App\UI\Cli\Presenter\StardustInfoJsonPresenter;
use App\UI\Cli\Presenter\StardustInfoTextPresenter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:stardust:info',
    aliases: ['stardust'],
    description: 'Stardust from catches, GO Battle League and gym berries, with and without a Star Piece',
)]
final class StardustInfoCommand extends Command
{
    public function __construct(
        private readonly StardustInfoHandler $handler,
        private readonly StardustInfoTextPresenter $text,
        private readonly StardustInfoJsonPresenter $json,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this->addOption('format', 'f', InputOption::VALUE_REQUIRED, 'Output format: text or json', 'text')->setHelp(
            <<<'HELP'
                What each source of Stardust pays, and what a Star Piece (+50%) adds:

                  catches      1, 10, 50 and 100, with and without a weather boost
                  battle sets  one win, five wins, and the end of the season by rank
                  gym berries  a full gym defender by defender, with the running total
                               to read a smaller gym off its first rows

                Examples:

                  <info>%command.full_name%</info>
                  <info>%command.full_name% --format=json</info>
                HELP,
        );
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = ($this->handler)();

        'json' === $input->getOption('format')
            ? $output->writeln($this->json->present($result))
            : $this->text->present(new SymfonyStyle($input, $output), $result);

        return Command::SUCCESS;
    }
}
