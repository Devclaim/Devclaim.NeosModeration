<?php

declare(strict_types=1);

namespace Devclaim\NeosModeration\Domain\Service;

use Devclaim\NeosModeration\Domain\Exception\InvalidModerationConfiguration;
use Devclaim\NeosModeration\Domain\Exception\UnknownModerationType;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\ObjectManagement\ObjectManagerInterface;

#[Flow\Scope('singleton')]
final class ModerationTypeRegistry
{
    /** @var array<string, array{handler?: mixed}> */
    #[Flow\InjectConfiguration(path: 'types', package: 'Devclaim.NeosModeration')]
    protected array $typeConfiguration = [];

    /** @var array<string, ModerationTypeHandlerInterface>|null */
    private ?array $handlers = null;

    public function __construct(private readonly ObjectManagerInterface $objectManager)
    {
    }

    public function get(string $typeKey): ModerationTypeHandlerInterface
    {
        return $this->all()[$typeKey] ?? throw UnknownModerationType::withKey($typeKey);
    }

    /** @return array<string, ModerationTypeHandlerInterface> */
    public function all(): array
    {
        if ($this->handlers !== null) {
            return $this->handlers;
        }

        $handlers = [];
        foreach ($this->typeConfiguration as $configuredKey => $configuration) {
            $className = $configuration['handler'] ?? null;
            if (!is_string($className) || $className === '') {
                throw new InvalidModerationConfiguration(sprintf(
                    'Moderation type "%s" must configure a non-empty handler class.',
                    $configuredKey,
                ));
            }
            $handler = $this->objectManager->get($className);
            if (!$handler instanceof ModerationTypeHandlerInterface) {
                throw new InvalidModerationConfiguration(sprintf(
                    'Handler "%s" for moderation type "%s" must implement %s.',
                    $className,
                    $configuredKey,
                    ModerationTypeHandlerInterface::class,
                ));
            }
            if ($handler->getTypeKey() !== $configuredKey) {
                throw new InvalidModerationConfiguration(sprintf(
                    'Handler "%s" reports key "%s", but is registered as "%s".',
                    $className,
                    $handler->getTypeKey(),
                    $configuredKey,
                ));
            }
            if (isset($handlers[$configuredKey])) {
                throw new InvalidModerationConfiguration(sprintf(
                    'Moderation type "%s" is registered twice.',
                    $configuredKey,
                ));
            }
            $handlers[$configuredKey] = $handler;
        }

        ksort($handlers);
        return $this->handlers = $handlers;
    }
}
