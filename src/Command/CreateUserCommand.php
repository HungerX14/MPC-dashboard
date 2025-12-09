<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Create a new user for the MPC Dashboard',
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly UserRepository $userRepository,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::OPTIONAL, 'User email address')
            ->addArgument('password', InputArgument::OPTIONAL, 'User password')
            ->addArgument('fullName', InputArgument::OPTIONAL, 'User full name')
            ->addOption('admin', null, InputOption::VALUE_NONE, 'Create user as admin (ROLE_ADMIN)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $helper = $this->getHelper('question');

        $io->title('MPC Dashboard - User Creation');

        // Get email
        $email = $input->getArgument('email');
        if (!$email) {
            $question = new Question('Email address: ');
            $question->setValidator(function ($value) {
                if (empty($value)) {
                    throw new \RuntimeException('Email cannot be empty');
                }
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new \RuntimeException('Invalid email format');
                }
                return $value;
            });
            $email = $helper->ask($input, $output, $question);
        }

        // Check if user already exists
        if ($this->userRepository->findByEmail($email)) {
            $io->error(sprintf('User with email "%s" already exists.', $email));
            return Command::FAILURE;
        }

        // Get password
        $password = $input->getArgument('password');
        if (!$password) {
            $question = new Question('Password: ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $question->setValidator(function ($value) {
                if (empty($value)) {
                    throw new \RuntimeException('Password cannot be empty');
                }
                if (strlen($value) < 8) {
                    throw new \RuntimeException('Password must be at least 8 characters');
                }
                return $value;
            });
            $password = $helper->ask($input, $output, $question);

            // Confirm password
            $confirmQuestion = new Question('Confirm password: ');
            $confirmQuestion->setHidden(true);
            $confirmQuestion->setHiddenFallback(false);
            $confirmPassword = $helper->ask($input, $output, $confirmQuestion);

            if ($password !== $confirmPassword) {
                $io->error('Passwords do not match.');
                return Command::FAILURE;
            }
        }

        // Get full name
        $fullName = $input->getArgument('fullName');
        if (!$fullName) {
            $question = new Question('Full name: ');
            $question->setValidator(function ($value) {
                if (empty($value)) {
                    throw new \RuntimeException('Full name cannot be empty');
                }
                return $value;
            });
            $fullName = $helper->ask($input, $output, $question);
        }

        // Create user
        $user = new User();
        $user->setEmail($email);
        $user->setFullName($fullName);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        // Set roles
        $roles = ['ROLE_USER'];
        if ($input->getOption('admin')) {
            $roles[] = 'ROLE_ADMIN';
        }
        $user->setRoles($roles);

        // Validate
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $io->error($error->getPropertyPath() . ': ' . $error->getMessage());
            }
            return Command::FAILURE;
        }

        // Save user
        $this->userRepository->save($user);

        $io->success(sprintf('User "%s" created successfully!', $email));

        $io->table(
            ['Property', 'Value'],
            [
                ['Email', $user->getEmail()],
                ['Full Name', $user->getFullName()],
                ['Roles', implode(', ', $user->getRoles())],
                ['Created At', $user->getCreatedAt()->format('Y-m-d H:i:s')],
            ]
        );

        return Command::SUCCESS;
    }
}
