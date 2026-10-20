<?php

namespace Tests\Support;

use Random\Engine\Mt19937;
use Random\Randomizer;
use RuntimeException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;

/**
 * SMTP stand-in that fails a seeded share of sends, so a chaos run can be replayed exactly.
 */
final class FlakyMailTransport extends AbstractTransport
{
    /** @var list<array{to: string, subject: string}> */
    public static array $delivered = [];

    public static int $failures = 0;

    private Randomizer $random;

    public function __construct(private int $failurePercent, int $seed)
    {
        parent::__construct();
        $this->random = new Randomizer(new Mt19937($seed));
    }

    public static function reset(): void
    {
        self::$delivered = [];
        self::$failures = 0;
    }

    protected function doSend(SentMessage $message): void
    {
        if ($this->random->getInt(1, 100) <= $this->failurePercent) {
            self::$failures++;

            throw new RuntimeException('Simulated SMTP outage');
        }

        $email = $message->getOriginalMessage();
        if ($email instanceof Email) {
            self::$delivered[] = ['to' => $email->getTo()[0]->getAddress(), 'subject' => (string) $email->getSubject()];
        }
    }

    public function __toString(): string
    {
        return 'flaky';
    }
}
