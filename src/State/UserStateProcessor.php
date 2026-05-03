<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserStateProcessor implements ProcessorInterface
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $em,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof User) {
            if ($data->getPlainPassword() !== null) {
                $data->setPassword(
                    $this->passwordHasher->hashPassword($data, $data->getPlainPassword())
                );
            }

            $avatar = $data->getAvatar();
            if ($avatar !== null && !preg_match('#^uploads/#', $avatar)) {
                $data->setAvatar(null);
            }
        }

        $this->em->persist($data);
        $this->em->flush();

        return $data;
    }
}
