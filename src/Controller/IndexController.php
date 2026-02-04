<?php
// src/Controller/LuckyController.php
namespace App\Controller;

use App\Dto\CompositeFormDto;
use App\Dto\SimpleFormDto;
use App\FormState\FormState;
use App\FormState\MapFormState;
use App\FormState\ViolationList;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class IndexController extends AbstractController
{

    #[Route('/')]
    public function index(): Response {
        return $this->render('index.html.twig', [

        ]);
    }

    /** @param FormState<SimpleFormDto> $payload */
    #[Route('/simple-form')]
    public function simpleForm(
        #[MapFormState(SimpleFormDto::class)] FormState $payload,
    ): Response {
        if ($payload->isValid()) {
            $submitted = print_r($payload->object, true);
            $this->addFlash('succes', "
                Submission ok!
                Submitted data:
                $submitted
            ");
            return $this->redirect('/');
        }

        return $this->render('simple_form.html.twig', [
            'values' => $payload->object,
            'errors' => $payload->violationList,
        ]);
    }

    /** @param FormState<CompositeFormDto> $payload */
    #[Route('/composite-form')]
    public function composedForm(
        #[MapFormState(CompositeFormDto::class)] FormState $payload,
    ): Response {
        if ($payload->isValid()) {

            return $this->redirect('/');
        }

        return $this->render('composite_form.html.twig', [
            'values' => $payload->object,
            'errors' => $payload->violationList,
        ]);
    }

    #[Route('/success')]
    public function success(): Response {
        return $this->render('index.html.twig', [

        ]);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    #[\Override]
    final protected function render(string $view, array $parameters = [], ?Response $response = null): Response
    {
        $response = parent::render($view, $parameters, $response);

        // If given parameters contain a ViolationList and this list
        // is not empty, then we change status code to 422
        // @see https://symfony.com/bundles/ux-turbo/current/index.html#3-form-response-code-changes
        if (200 === $response->getStatusCode()) {
            foreach ($parameters as $v) {
                if ($v instanceof ViolationList && \count($v)) {
                    $response->setStatusCode(422);
                    break;
                }
            }
        }

        return $response;
    }
}
