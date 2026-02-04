<?php

declare(strict_types=1);

namespace App\FormState;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @template T of object
 */
 #[AsTaggedItem]
final class FormStateValueResolver implements ValueResolverInterface
{
    public function __construct(
        private ValidatorInterface $validator,
        private DenormalizerInterface $denormalizer,
        private CsrfTokenManagerInterface $csrfTokenManager,
    ) {}

    /**
     * @return iterable<FormState<T>>
     */
    #[\Override]
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (FormState::class !== $argument->getType()) {
            return [];
        }

        $type = null;
        foreach ($argument->getAttributes() as $attribute) {
            if ($attribute instanceof MapFormState) {
                /** @var class-string<T> $type */
                $type = $attribute->type;
            }
        }
        if (!$type) {
            throw new \LogicException('Argument "' . $argument->getName() . '" needs a "' . MapFormState::class . '" attribute.');
        }

        /** @var ?T $object */
        $object = null;

        if ($request->isMethod('POST')) {
            try {
                $data = $request->getPayload()->all();
            } catch (\Throwable $e) {
                return [new FormState(
                    $object,
                    (new ViolationList($type))->add("La requête n'a pas pu être décodée."),
                )];
            }

            if (!$data['token'] || !$this->csrfTokenManager->isTokenValid(new CsrfToken('', $data['token']))) {
                return [new FormState(
                    $object,
                    (new ViolationList($type))->add("Le jeton CSRF est invalide."),
                )];
            }

            $this->handleNullableValues($data, $type);

            /** @var ?T $object */
            $object = $this->denormalizer->denormalize(
                data: $data,
                type: $type,
                context: [
                    AbstractNormalizer::FILTER_BOOL => true,
                    DateTimeNormalizer::FORMAT_KEY => 'd/m/Y',
                    ObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true,
                ],
            );
        }

        $violationList = new ViolationList($type);
        if ($object) {
            foreach ($this->validator->validate($object) as $violation) {
                $violationList->add((string) $violation->getMessage(), $violation->getPropertyPath());
            }
        }

        return [new FormState(
            $object,
            $violationList
        )];
    }

    /**
     * @param mixed[] &$data
     */
    private function handleNullableValues(array &$data, string $type): void
    {
        // @phpstan-ignore-next-line
        $classMetadata = new \ReflectionClass($type);
        $constructorMetadata = $classMetadata->getConstructor();

        foreach ($constructorMetadata?->getParameters() ?? [] as $paramMetadata) {
            if (!$paramMetadata->allowsNull()) {
                continue;
            }
            if (!$paramMetadata->getAttributes(EmptyAsNull::class)) {
                continue;
            }
            if (!$paramMetadata->getType() instanceof \ReflectionNamedType) {
                throw new \LogicException(\sprintf(
                    "Invalid usage of %s attribute: not allowed for parameters typed with unions or intersections.",
                    EmptyAsNull::class,
                ));
            }

            $valueName = $paramMetadata->getName();

            // If the parameter expects to be populated from a value
            // named differently than itself, use the value name instead.
            if ($serdeNameAttr = $paramMetadata->getAttributes(SerializedName::class)[0] ?? false) {
                /** @var SerializedName $serdeNameAttr */
                $valueName = $serdeNameAttr->serializedName;
            }

            if (!\array_key_exists($valueName, $data)) {
                continue;
            }

            $paramType = $paramMetadata->getType()->getName();

            if ('' === $data[$valueName] && \in_array($paramType, ['int', 'string'])) {
                $data[$valueName] = null;
            } elseif ([] === $data[$valueName] && 'array' === $paramType) {
                $data[$valueName] = null;
            }
        }
    }
}
