<?php

namespace App\Service\Action;

use App\Entity\Action;
use App\Entity\EntityManagerFactory;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

final class ActionCreateService
{
    private EntityManagerInterface $entityManager;

    public function __construct(?EntityManagerInterface $entityManager = null)
    {
        $this->entityManager = $entityManager ?? EntityManagerFactory::getEntityManager();
    }

    /**
     * The action types that can be created, discriminator => display label.
     * Sourced from the STI DiscriminatorMap so it never drifts from the entity.
     *
     * @return array<string, string>
     */
    public function availableTypes(): array
    {
        $types = [];
        foreach (array_keys($this->entityManager->getClassMetadata(Action::class)->discriminatorMap) as $type) {
            $types[(string) $type] = ucfirst((string) $type);
        }

        return $types;
    }

    /**
     * Create a new, empty action of the given STI type. text is NOT NULL on the
     * table so it defaults to ''; the icon (an RPG-Awesome class, e.g.
     * ra-crossed-swords) and category are optional.
     */
    public function create(string $type, string $name, string $displayName, int $level, ?string $category = null, string $icon = ''): Action
    {
        $map = $this->entityManager->getClassMetadata(Action::class)->discriminatorMap;
        if (!isset($map[$type])) {
            throw new InvalidArgumentException("Type d'action inconnu : {$type}.");
        }

        $name = trim($name);
        if ($name === '') {
            throw new InvalidArgumentException("Le nom de l'action est requis.");
        }

        $class = $map[$type];
        /** @var Action $action */
        $action = new $class();
        $action->setName($name);
        $action->setDisplayName(trim($displayName) !== '' ? trim($displayName) : $name);
        $action->setLevel($level);
        $action->setIcon(trim($icon));
        $action->setText('');
        if ($category !== null && trim($category) !== '') {
            $action->setCategory(trim($category));
        }

        $this->entityManager->persist($action);
        $this->entityManager->flush();

        return $action;
    }
}
