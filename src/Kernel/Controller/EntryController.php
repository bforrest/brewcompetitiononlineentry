<?php

declare(strict_types=1);

namespace Bcoem\Kernel\Controller;

use Bcoem\Domain\Entry\Command\CreateEntryCommand;
use Bcoem\Domain\Entry\Command\UpdateEntryCommand;
use Bcoem\Domain\Entry\Exception\EntryException;
use Bcoem\Domain\Entry\Service\EntryService;
use Bcoem\Domain\Entry\ValueObject\BrewerId;
use Bcoem\Domain\Entry\ValueObject\EntryId;
use Bcoem\Security\Identity;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Routing\RouteContext;

/**
 * Controller for entry (brewing) workflow.
 * Handles HTTP request/response; delegates business logic to EntryService.
 */
final class EntryController
{
    public function __construct(
        private EntryService $entryService,
    ) {
    }

    /**
     * GET /entries — show create entry form.
     */
    public function getCreateForm(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $identity = $request->getAttribute('identity') ?? throw new \RuntimeException('No identity in request');

        // TODO: Fetch styles, check entry window, render template
        // For now, return 200 as placeholder

        $response->getBody()->write('Create Entry Form (TODO)');
        return $response->withStatus(200);
    }

    /**
     * GET /entries/{id}/edit — show edit entry form.
     */
    public function getEditForm(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $identity = $request->getAttribute('identity') ?? throw new \RuntimeException('No identity in request');
        $entryId = EntryId::from((int) ($args['id'] ?? 0));

        // TODO: Load entry, render form with pre-filled data
        // For now, return 200 as placeholder

        $response->getBody()->write('Edit Entry Form (TODO)');
        return $response->withStatus(200);
    }

    /**
     * POST /entries — create a new entry.
     */
    public function postCreate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $identity = $request->getAttribute('identity') ?? throw new \RuntimeException('No identity in request');
        $data = $request->getParsedBody() ?? [];

        try {
            $cmd = new CreateEntryCommand($data);
            $entryId = $this->entryService->create($cmd, $identity);

            // Success: redirect to entry detail or list
            return $response
                ->withStatus(302)
                ->withHeader('Location', '/entries/my');
        } catch (EntryException $e) {
            // Expected business error: re-render form with errors
            return $response
                ->withStatus($e->getHttpStatus())
                ->withHeader('Content-Type', 'application/json');
        } catch (\Symfony\Component\Validator\Exception\ValidationFailedException $e) {
            // Field validation error: return 422 with field errors
            return $response
                ->withStatus(422)
                ->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * POST /entries/{id} — update an entry.
     */
    public function postUpdate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $identity = $request->getAttribute('identity') ?? throw new \RuntimeException('No identity in request');
        $data = $request->getParsedBody() ?? [];
        $data['id'] = (int) ($args['id'] ?? 0);

        try {
            $cmd = new UpdateEntryCommand($data);
            $this->entryService->update($cmd, $identity);

            // Success: redirect
            return $response
                ->withStatus(302)
                ->withHeader('Location', '/entries/my');
        } catch (EntryException $e) {
            return $response
                ->withStatus($e->getHttpStatus())
                ->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * DELETE /entries/{id} — delete an entry.
     */
    public function postDelete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $identity = $request->getAttribute('identity') ?? throw new \RuntimeException('No identity in request');
        $entryId = EntryId::from((int) ($args['id'] ?? 0));

        try {
            $this->entryService->delete($entryId, $identity);

            return $response
                ->withStatus(302)
                ->withHeader('Location', '/entries/my');
        } catch (EntryException $e) {
            return $response->withStatus($e->getHttpStatus());
        }
    }

    /**
     * GET /entries/my — list current user's entries.
     */
    public function listEntries(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $identity = $request->getAttribute('identity') ?? throw new \RuntimeException('No identity in request');

        // TODO: Get brewer ID from identity, fetch entries, render template
        // For now, return 200 as placeholder

        $response->getBody()->write('Entry List (TODO)');
        return $response->withStatus(200);
    }
}
