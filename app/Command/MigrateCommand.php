<?php

namespace Leantime\Command;

use Illuminate\Contracts\Container\BindingResolutionException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Leantime\Domain\Install\Repositories\Install;
use Exception;
use Leantime\Domain\Users\Repositories\Users;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'db:migrate',
    description: 'Runs and pending Database Migrations',
)]
class MigrateCommand extends Command
{
    /**
     * @return void
     */
    protected function configure(): void
    {
        parent::configure();

        $this->setAliases(["db:install", "db:update"]);

        $this->addOption('silent', null, InputOption::VALUE_OPTIONAL, "Silently Handle Migrations. DOES NOT CREATE ADMIN ACCOUNT IF NEEDED", "false");
        $this->addOption('email', null, InputOption::VALUE_OPTIONAL, "User's Email", null)
            ->addOption('password', null, InputOption::VALUE_OPTIONAL, "User's Password", null)
            ->addOption('company-name', null, InputOption::VALUE_OPTIONAL, "Company Name", null)
            ->addOption('first-name', null, InputOption::VALUE_OPTIONAL, "User's First name", null)
            ->addOption('last-name', null, InputOption::VALUE_OPTIONAL, "User's Last Name", null);
    }

    /**
     * Execute the command
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @return int 0 if everything went fine, or an exit code.
     * @throws BindingResolutionException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        define('BASE_URL', "");
        define('CURRENT_URL', "");

        $install = app()->make(Install::class);
        $io = new SymfonyStyle($input, $output);
        $silent = $input->getOption("silent") === "true";
        try {
            if (!$install->checkIfInstalled()) {
                if ($silent) {
                    $setupConfig = array(
                        "email" => "admin@leantime.io",
                        "password" => "",
                        "firstname" => "",
                        "lastname" => "",
                        "company" => "Leantime",
                    );
                } else {
                    $email = $input->getOption('email');
                    $password = $input->getOption('password');
                    $companyName = $input->getOption('company-name');
                    $firstName = $input->getOption('first-name');
                    $lastName = $input->getOption('last-name');

                    $adminEmail = $email ?? $io->ask("Admin Email");
                    $adminPassword = $password ?? $io->askHidden("Admin Password");
                    $adminFirstName = $firstName ?? $io->ask("Admin First Name");
                    $adminLastName = $lastName ?? $io->ask("Admin Last Name");
                    $companyName = $companyName ?? $io->ask("Company Name");

                    $setupConfig = array(
                        "email" => $adminEmail,
                        "password" => $adminPassword,
                        "firstname" => $adminFirstName,
                        "lastname" => $adminLastName,
                        "company" => $companyName,
                    );
                }

                $io->text("Installing DB For First Time");
                $installStatus = $install->setupDB($setupConfig);

                if ($installStatus !== true) {
                    $io->text($installStatus);
                    return Command::FAILURE;
                }
                if ($silent) {
                    $usersRepo = app()->make(Users::class);
                    $userId = array_values($usersRepo->getUserByEmail($adminEmail))[0];
                    $usersRepo->deleteUser($userId);
                }
                $io->text("Successfully Installed DB");
            }
            $success = $install->updateDB();
            if (!$success) {
                throw new Exception("Migration Failed; Please Check Logs");
            }
        } catch (Exception $ex) {
            $io->error($ex);
            return Command::FAILURE;
        }

        $io->success("Database Successfully Migrated");
        return Command::SUCCESS;
    }
}
