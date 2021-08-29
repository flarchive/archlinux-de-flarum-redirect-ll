<?php

namespace ArchLinux\RedirectLL\Middleware;

use Flarum\Discussion\Discussion;
use Flarum\Discussion\DiscussionRepository;
use Flarum\Http\SlugManager;
use Flarum\Http\UrlGenerator;
use Flarum\Tags\TagRepository;
use Flarum\User\User;
use Flarum\User\UserRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class LLRedirect implements MiddlewareInterface
{
    public function __construct(
        private UrlGenerator $urlGenerator,
        private TagRepository $tagRepository,
        private UserRepository $userRepository,
        private DiscussionRepository $discussionRepository,
        private SlugManager $slugManager
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (
            $request->getUri()->getPath() === '/' && in_array(
                $request->getMethod(),
                ['GET', 'HEAD']
            ) && $request->getUri()->getQuery()
        ) {
            return $this->handleRequest($request, $handler);
        }

        return $handler->handle($request);
    }

    private function handleRequest(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $queryString = $request->getUri()->getQuery();
        // Fix requests that were urlencoded twice
        if (preg_match('/%[0-9A-F]+/i', $queryString)) {
            $queryString = urldecode($queryString);
        }
        // Support ; parameter delimiter
        $queryString = str_replace(';', '&', $queryString);
        $query = [];
        parse_str($queryString, $query);

        if (!isset($query['page'])) {
            return $handler->handle($request);
        }

        $path = $this->urlGenerator->to('forum')->route('default');
        $status = 302;

        switch ($query['page']) {
            case 'Postings':
                if (isset($query['thread'])) {
                    try {
                        $params = [];
                        $discussion = $this->discussionRepository->findOrFail(intval($query['thread']));
                        $params['id'] = $this->slugManager->forResource(Discussion::class)->toSlug($discussion);
                        if (isset($query['post'])) {
                            $postIndex = intval($query['post']);
                            if ($postIndex === -1) {
                                $params['near'] = $discussion->last_post_number;
                            } else {
                                $params['near'] = $postIndex + 1;
                            }
                        }
                        $path = $this->urlGenerator->to('forum')
                            ->route('discussion', $params);
                        $status = 301;
                    } catch (ModelNotFoundException $e) {
                        $status = 404;
                    }
                }
                break;
            case 'ShowUser':
            case 'UserRecent':
                if (isset($query['user'])) {
                    try {
                        $user = $this->userRepository->findOrFail(intval($query['user']));
                        $path = $this->urlGenerator->to('forum')->route(
                            'user',
                            ['username' => $this->slugManager->forResource(User::class)->toSlug($user)]
                        );
                        $status = 301;
                    } catch (ModelNotFoundException $e) {
                        $status = 404;
                    }
                }
                break;
            case 'Threads':
                if (isset($query['forum'])) {
                    try {
                        $tag = $this->tagRepository->findOrFail(intval($query['forum']));
                        $path = $this->urlGenerator->to('forum')
                            ->route('tag', ['slug' => $tag->slug]);
                        $status = 301;
                    } catch (ModelNotFoundException $e) {
                        // Implicitly redirect to forum index
                    }
                }
                break;
            case 'GetAttachment':
            case 'GetAttachmentThumb':
            case 'GetAvatar':
            case 'GetImage':
            case 'GetRecent':
                $status = 404;
                break;
        }

        if ($status === 404) {
            return new Response(status: 404);
        }

        return new RedirectResponse($path, $status);
    }
}
