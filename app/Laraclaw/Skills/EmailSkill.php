<?php

namespace App\Laraclaw\Skills;

use App\Laraclaw\Skills\Contracts\SkillInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class EmailSkill implements SkillInterface, Tool
{
    protected ?string $imapHost = null;

    protected ?int $imapPort = null;

    protected ?string $imapUser = null;

    protected ?string $imapPassword = null;

    protected $imapConnection = null;

    public function name(): string
    {
        return 'email';
    }

    public function description(): Stringable|string
    {
        return 'Manage email operations including listing, reading, and sending emails. Requires IMAP configuration for reading.';
    }

    public function execute(array $parameters): string
    {
        $action = $parameters['action'] ?? 'list';

        return match ($action) {
            'list' => $this->listEmails($parameters),
            'read' => $this->readEmail($parameters),
            'send' => $this->sendEmail($parameters),
            'search' => $this->searchEmails($parameters),
            'delete' => $this->deleteEmail($parameters),
            'folders' => $this->listFolders(),
            default => "Unknown action: {$action}. Available actions: list, read, send, search, delete, folders",
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->description('The email action to perform: list, read, send, search, delete, folders')
                ->enum(['list', 'read', 'send', 'search', 'delete', 'folders']),
            'folder' => $schema->string()
                ->description('The email folder/mailbox (e.g., INBOX, Sent, Drafts)'),
            'limit' => $schema->integer()
                ->description('Maximum number of emails to return (default: 10)'),
            'email_id' => $schema->integer()
                ->description('The email ID to read or delete'),
            'to' => $schema->string()
                ->description('Recipient email address for sending'),
            'subject' => $schema->string()
                ->description('Email subject for sending'),
            'body' => $schema->string()
                ->description('Email body content for sending'),
            'query' => $schema->string()
                ->description('Search query for searching emails'),
        ];
    }

    public function toTool(): Tool
    {
        return $this;
    }

    public function handle(Request $request): Stringable|string
    {
        return $this->execute($request->all());
    }

    /**
     * Configure IMAP connection.
     */
    public function configure(string $host, int $port, string $user, string $password): self
    {
        $this->imapHost = $host;
        $this->imapPort = $port;
        $this->imapUser = $user;
        $this->imapPassword = $password;

        return $this;
    }

    /**
     * Connect to IMAP server.
     */
    protected function connect(string $folder = 'INBOX'): bool
    {
        if (! extension_loaded('imap')) {
            return false;
        }

        $connectionString = "{{$this->imapHost}:{$this->imapPort}/imap/ssl/novalidate-cert}".$folder;

        $this->imapConnection = @\imap_open($connectionString, $this->imapUser, $this->imapPassword);

        return $this->imapConnection !== false;
    }

    /**
     * Disconnect from IMAP server.
     */
    protected function disconnect(): void
    {
        if ($this->imapConnection) {
            @\imap_close($this->imapConnection);
            $this->imapConnection = null;
        }
    }

    /**
     * List emails from a folder.
     */
    protected function listEmails(array $params): string
    {
        if (! $this->hasImapConfig()) {
            return 'Error: IMAP not configured. Please set IMAP_HOST, IMAP_PORT, IMAP_USER, and IMAP_PASSWORD in your environment.';
        }

        $folder = $params['folder'] ?? 'INBOX';
        $limit = $params['limit'] ?? 10;

        if (! $this->connect($folder)) {
            return 'Error: Failed to connect to IMAP server. Check your credentials.';
        }

        try {
            $emails = \imap_search($this->imapConnection, 'ALL', SE_UID);

            if (! $emails) {
                return "No emails found in {$folder}.";
            }

            $emails = array_slice(array_reverse($emails), 0, $limit);
            $results = [];

            foreach ($emails as $emailId) {
                $overview = \imap_fetch_overview($this->imapConnection, $emailId, FT_UID)[0];
                $results[] = [
                    'id' => $emailId,
                    'from' => $overview->from ?? 'Unknown',
                    'subject' => $overview->subject ?? '(No Subject)',
                    'date' => $overview->date ?? '',
                    'seen' => ($overview->seen ?? 0) === 1,
                ];
            }

            $output = "Emails in {$folder}:\n\n";
            foreach ($results as $email) {
                $status = $email['seen'] ? '✓' : '●';
                $output .= "{$status} [{$email['id']}] From: {$email['from']}\n";
                $output .= "    Subject: {$email['subject']}\n";
                $output .= "    Date: {$email['date']}\n\n";
            }

            return $output;
        } finally {
            $this->disconnect();
        }
    }

    /**
     * Read a specific email.
     */
    protected function readEmail(array $params): string
    {
        if (! $this->hasImapConfig()) {
            return 'Error: IMAP not configured.';
        }

        $emailId = $params['email_id'] ?? null;
        $folder = $params['folder'] ?? 'INBOX';

        if (! $emailId) {
            return 'Error: email_id is required for reading an email.';
        }

        if (! $this->connect($folder)) {
            return 'Error: Failed to connect to IMAP server.';
        }

        try {
            $overview = \imap_fetch_overview($this->imapConnection, $emailId, FT_UID)[0] ?? null;

            if (! $overview) {
                return "Email {$emailId} not found.";
            }

            $body = $this->getEmailBody($emailId);

            $output = "Email #{$emailId}\n";
            $output .= str_repeat('=', 50)."\n";
            $output .= "From: {$overview->from}\n";
            $output .= "To: {$overview->to}\n";
            $output .= "Subject: {$overview->subject}\n";
            $output .= "Date: {$overview->date}\n";
            $output .= str_repeat('-', 50)."\n";
            $output .= $body;

            return $output;
        } finally {
            $this->disconnect();
        }
    }

    /**
     * Get the email body.
     */
    protected function getEmailBody(int $emailId): string
    {
        $structure = \imap_fetchstructure($this->imapConnection, $emailId, FT_UID);

        if (! $structure) {
            return '(Unable to fetch email body)';
        }

        // Simple text body extraction
        if ($structure->type === 0 && isset($structure->subtype)) {
            $body = \imap_fetchbody($this->imapConnection, $emailId, 1, FT_UID);

            if ($structure->encoding === 3) {
                $body = base64_decode($body);
            } elseif ($structure->encoding === 4) {
                $body = quoted_printable_decode($body);
            }

            return trim($body);
        }

        // Multipart message
        if ($structure->type === 1 && isset($structure->parts)) {
            foreach ($structure->parts as $index => $part) {
                if ($part->type === 0 && in_array(strtolower($part->subtype ?? ''), ['plain', 'text'])) {
                    $body = \imap_fetchbody($this->imapConnection, $emailId, $index + 1, FT_UID);

                    if ($part->encoding === 3) {
                        $body = base64_decode($body);
                    } elseif ($part->encoding === 4) {
                        $body = quoted_printable_decode($body);
                    }

                    return trim($body);
                }
            }
        }

        return '(No text content found)';
    }

    /**
     * Send an email.
     */
    protected function sendEmail(array $params): string
    {
        $to = $params['to'] ?? null;
        $subject = $params['subject'] ?? '(No Subject)';
        $body = $params['body'] ?? '';

        if (! $to) {
            return 'Error: "to" email address is required.';
        }

        try {
            Mail::raw($body, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });

            Log::info('EmailSkill: Email sent', ['to' => $to, 'subject' => $subject]);

            return "Email sent successfully to {$to}.";
        } catch (\Exception $e) {
            Log::error('EmailSkill: Failed to send email', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);

            return "Error sending email: {$e->getMessage()}";
        }
    }

    /**
     * Search emails.
     */
    protected function searchEmails(array $params): string
    {
        if (! $this->hasImapConfig()) {
            return 'Error: IMAP not configured.';
        }

        $query = $params['query'] ?? '';
        $folder = $params['folder'] ?? 'INBOX';
        $limit = $params['limit'] ?? 10;

        if (! $query) {
            return 'Error: query is required for searching.';
        }

        if (! $this->connect($folder)) {
            return 'Error: Failed to connect to IMAP server.';
        }

        try {
            $emails = \imap_search($this->imapConnection, "TEXT \"{$query}\"", SE_UID);

            if (! $emails) {
                return "No emails found matching '{$query}'.";
            }

            $emails = array_slice(array_reverse($emails), 0, $limit);
            $results = [];

            foreach ($emails as $emailId) {
                $overview = \imap_fetch_overview($this->imapConnection, $emailId, FT_UID)[0];
                $results[] = [
                    'id' => $emailId,
                    'from' => $overview->from ?? 'Unknown',
                    'subject' => $overview->subject ?? '(No Subject)',
                    'date' => $overview->date ?? '',
                ];
            }

            $output = "Search results for '{$query}':\n\n";
            foreach ($results as $email) {
                $output .= "[{$email['id']}] From: {$email['from']}\n";
                $output .= "    Subject: {$email['subject']}\n";
                $output .= "    Date: {$email['date']}\n\n";
            }

            return $output;
        } finally {
            $this->disconnect();
        }
    }

    /**
     * Delete an email.
     */
    protected function deleteEmail(array $params): string
    {
        if (! $this->hasImapConfig()) {
            return 'Error: IMAP not configured.';
        }

        $emailId = $params['email_id'] ?? null;
        $folder = $params['folder'] ?? 'INBOX';

        if (! $emailId) {
            return 'Error: email_id is required for deleting an email.';
        }

        if (! $this->connect($folder)) {
            return 'Error: Failed to connect to IMAP server.';
        }

        try {
            \imap_delete($this->imapConnection, $emailId, FT_UID);
            \imap_expunge($this->imapConnection);

            return "Email {$emailId} deleted successfully.";
        } catch (\Exception $e) {
            return "Error deleting email: {$e->getMessage()}";
        } finally {
            $this->disconnect();
        }
    }

    /**
     * List available folders.
     */
    protected function listFolders(): string
    {
        if (! $this->hasImapConfig()) {
            return 'Error: IMAP not configured.';
        }

        if (! $this->connect()) {
            return 'Error: Failed to connect to IMAP server.';
        }

        try {
            $folders = \imap_list($this->imapConnection, "{{$this->imapHost}:{$this->imapPort}}", '*');

            if (! $folders) {
                return 'No folders found.';
            }

            $output = "Available folders:\n";
            foreach ($folders as $folder) {
                $name = str_replace("{{$this->imapHost}:{$this->imapPort}}", '', $folder);
                $output .= "  • {$name}\n";
            }

            return $output;
        } finally {
            $this->disconnect();
        }
    }

    /**
     * Check if IMAP is configured.
     */
    protected function hasImapConfig(): bool
    {
        $this->imapHost = $this->imapHost ?? env('IMAP_HOST');
        $this->imapPort = $this->imapPort ?? env('IMAP_PORT', 993);
        $this->imapUser = $this->imapUser ?? env('IMAP_USER');
        $this->imapPassword = $this->imapPassword ?? env('IMAP_PASSWORD');

        return $this->imapHost && $this->imapUser && $this->imapPassword;
    }
}
