<?php declare(strict_types=1);

namespace Shopware\Core\System\Language;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommandInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Validation\ConstraintViolationException;
use Shopware\Core\Framework\Validation\WriteCommandValidatorInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class LanguageValidator implements WriteCommandValidatorInterface
{
    public const PARENT_HAS_PARENT_VIOLATION = 'parent_has_parent_violation';

    public const DELETE_DEFAULT_LANGUAGE_VIOLATION = 'delete_default_language_violation';

    public const DEFAULT_LANGUAGES = [Defaults::LANGUAGE_EN, Defaults::LANGUAGE_DE];

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function postValidate(array $commands, WriteContext $context): void
    {
        $affectedIds = $this->getAffectedIds($commands);
        if (\count($affectedIds) === 0) {
            return;
        }

        $tenantId = Uuid::fromStringToBytes($context->getContext()->getTenantId());
        $statement = $this->connection->executeQuery('
            SELECT child.id 
            FROM language child
            INNER JOIN language parent ON parent.id = child.parent_id AND parent.tenant_id = child.tenant_id
            WHERE child.tenant_id = :tenant_id
            AND (child.id IN (:ids) OR child.parent_id IN (:ids))
            AND parent.parent_id IS NOT NULL',
            ['tenant_id' => $tenantId, 'ids' => $affectedIds],
            ['tenant_id' => ParameterType::STRING, 'ids' => Connection::PARAM_STR_ARRAY]
        );
        $ids = $statement->fetchAll(FetchMode::COLUMN);

        $violations = new ConstraintViolationList();
        foreach ($ids as $binId) {
            $id = Uuid::fromBytesToHex($binId);
            $violations->add(
                $this->buildViolation(
                    'Language inheritance limit for the child {{ id }} exceeded. A Language must not be nested deeper than one level.',
                    ['{{ id }}' => $id],
                    null,
                    '/' . $id . '/parentId',
                    $id,
                    self::PARENT_HAS_PARENT_VIOLATION
                )
            );
        }

        $this->tryToThrow($violations);
    }

    /**
     * @param WriteCommandInterface[] $commands
     */
    public function preValidate(array $commands, WriteContext $context): void
    {
        $violations = new ConstraintViolationList();

        foreach ($commands as $command) {
            if (!$command instanceof DeleteCommand || $command->getDefinition() !== LanguageDefinition::class) {
                continue;
            }

            $pk = $command->getPrimaryKey();
            $id = \strtolower(Uuid::fromBytesToHex($pk['id']));
            if (!\in_array($id, [Defaults::LANGUAGE_EN, Defaults::LANGUAGE_DE])) {
                continue;
            }

            $violations->add(
                $this->buildViolation(
                    'The default language {{ id }} cannot be deleted.',
                    ['{{ id }}' => $id],
                    null,
                    '/' . $id,
                    $id,
                    self::DELETE_DEFAULT_LANGUAGE_VIOLATION
                )
            );
        }

        $this->tryToThrow($violations);
    }

    /**
     * @param WriteCommandInterface[] $commands
     *
     * @return string[]
     */
    private function getAffectedIds(array $commands): array
    {
        $ids = [];
        foreach ($commands as $command) {
            if ($command->getDefinition() !== LanguageDefinition::class) {
                continue;
            }
            if ($command instanceof InsertCommand || $command instanceof UpdateCommand) {
                $ids[] = $command->getPrimaryKey()['id'];
            }
        }

        return $ids;
    }

    /**
     * @throws ConstraintViolationException
     */
    private function tryToThrow(ConstraintViolationListInterface $violations): void
    {
        if ($violations->count() > 0) {
            throw new ConstraintViolationException($violations);
        }
    }

    private function buildViolation(
        string $messageTemplate,
        array $parameters,
        $root = null,
        string $propertyPath = null,
        $invalidValue = null,
        $code = null
    ): ConstraintViolationInterface {
        return new ConstraintViolation(
            str_replace(array_keys($parameters), array_values($parameters), $messageTemplate),
            $messageTemplate,
            $parameters,
            $root,
            $propertyPath,
            $invalidValue,
            $plural = null,
            $code,
            $constraint = null,
            $cause = null
        );
    }
}