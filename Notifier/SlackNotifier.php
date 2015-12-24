<?php

namespace Elao\ErrorNotifierBundle\Notifier;

use CL\Slack\Payload\ChatPostMessagePayload;
use CL\Slack\Transport\ApiClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\FlattenException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Templating\EngineInterface;

class SlackNotifier implements NotifierInterface
{
    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * @var EngineInterface
     */
    protected $templating;

    /**
     * @var string
     */
    protected $channel;

    /**
     * @var ApiClient
     */
    protected $apiClient;

    /**
     * @param ApiClient $apiClient
     * @param TokenStorageInterface $tokenStorage
     * @param EngineInterface $templating
     * @param $channel
     */
    public function __construct(
        ApiClient $apiClient,
        TokenStorageInterface $tokenStorage,
        EngineInterface $templating,
        $channel
    ) {
        $this->tokenStorage     = $tokenStorage;
        $this->apiClient        = $apiClient;
        $this->templating       = $templating;

        if (strpos($channel, '#') !== 0) {
            $channel = sprintf('#%s', $channel);
        }

        $this->channel          = $channel;
    }

    /**
     * {@inheritdoc}
     */
    public function notify(
        FlattenException $exception,
        Request $request = null,
        $context = null,
        Command $command = null,
        InputInterface $commandInput = null
    ) {
        $text = $this->templating->render('ElaoErrorNotifierBundle:Slack:text.txt.twig', array(
            'exception'       => $exception,
            'request'         => $request,
            'status_code'     => $exception->getCode(),
            'context'         => $context,
            'command'         => $command,
            'command_input'   => $commandInput
        ));

        $payload = new ChatPostMessagePayload();
        $payload->setChannel($this->channel);
        $payload->setText($text);
        $payload->setUsername(sprintf('%s (bot)', $this->getUsername($exception)));
        $payload->setIconEmoji('skull');

        $this->apiClient->send($payload);
    }

    /**
     * @param FlattenException $exception
     * @return string
     */
    private function getUsername(FlattenException $exception)
    {
        if (null !== $token = $this->tokenStorage->getToken()) {
            return $token->getUsername();
        }

        return $exception->getStatusCode();
    }
}
