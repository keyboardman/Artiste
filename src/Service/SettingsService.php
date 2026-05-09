<?php

namespace App\Service;

use App\Entity\SiteSetting;
use App\Repository\SiteSettingRepository;
use Doctrine\ORM\EntityManagerInterface;

class SettingsService
{
    public function __construct(
        private SiteSettingRepository $repo,
        private EntityManagerInterface $em,
    ) {}

    public function get(string $key, ?string $default = null): ?string
    {
        $setting = $this->repo->find($key);
        return $setting?->getValue() ?? $default;
    }

    public function set(string $key, ?string $value): void
    {
        $setting = $this->repo->find($key);
        if (!$setting) {
            $setting = new SiteSetting($key, $value);
            $this->em->persist($setting);
        } else {
            $setting->setValue($value);
        }
        $this->em->flush();
    }

    /** @return array<string, ?string> */
    public function all(): array
    {
        $out = [];
        foreach ($this->repo->findAll() as $setting) {
            $out[$setting->getKey()] = $setting->getValue();
        }
        return $out;
    }

    /** @param array<string, ?string> $values */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            $setting = $this->repo->find($key);
            if (!$setting) {
                $this->em->persist(new SiteSetting($key, $value));
            } else {
                $setting->setValue($value);
            }
        }
        $this->em->flush();
    }
}
