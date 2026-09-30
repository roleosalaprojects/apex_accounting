<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Company;
use App\Support\Money;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * A document going out by email: a covering message, a few key facts, an
 * optional table, and the PDF attached. Each kind of document fills in the
 * subject, the facts and the attachment.
 */
abstract class DocumentMail extends Mailable
{
    public function __construct(public readonly string $message) {}

    abstract public function company(): Company;

    abstract public function partyName(): string;

    abstract protected function subjectLine(): string;

    /**
     * @return array<string, string>
     */
    abstract protected function facts(): array;

    /**
     * @return array{name: string, pdf: string}|null
     */
    abstract protected function pdf(): ?array;

    /**
     * @return array{columns: list<string>, rows: list<list<string>>}|null
     */
    protected function table(): ?array
    {
        return null;
    }

    /** From, and replies to, the company's own address when it has one; else the app's default sender. */
    public function envelope(): Envelope
    {
        $company = $this->company();
        $sender = filled($company->email) ? new Address((string) $company->email, $company->name) : null;

        return new Envelope(
            from: $sender,
            replyTo: $sender === null ? [] : [$sender],
            subject: $this->subjectLine(),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.document', with: [
            'company' => $this->company(),
            'party' => $this->partyName(),
            'body' => $this->message,
            'facts' => $this->facts(),
            'table' => $this->table(),
        ]);
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $pdf = $this->pdf();
        if ($pdf === null) {
            return [];
        }

        return [Attachment::fromData(fn (): string => $pdf['pdf'], $pdf['name'])->withMime('application/pdf')];
    }

    protected function peso(int $minor): string
    {
        return Money::of($minor)->format();
    }
}
