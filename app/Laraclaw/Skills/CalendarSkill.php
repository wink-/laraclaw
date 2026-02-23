<?php

namespace App\Laraclaw\Skills;

use App\Laraclaw\Skills\Contracts\SkillInterface;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Cache;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CalendarSkill implements SkillInterface, Tool
{
    protected string $cacheKey = 'laraclaw.calendar.events';

    public function name(): string
    {
        return 'calendar';
    }

    public function description(): Stringable|string
    {
        return 'Manage calendar events including creating, listing, updating, and deleting events. Can also generate ICS files.';
    }

    public function execute(array $parameters): string
    {
        $action = $parameters['action'] ?? 'list';

        return match ($action) {
            'list' => $this->listEvents($parameters),
            'create' => $this->createEvent($parameters),
            'update' => $this->updateEvent($parameters),
            'delete' => $this->deleteEvent($parameters),
            'find' => $this->findEvents($parameters),
            'ics' => $this->generateIcs($parameters),
            'today' => $this->getTodayEvents(),
            'week' => $this->getWeekEvents(),
            default => "Unknown action: {$action}. Available actions: list, create, update, delete, find, ics, today, week",
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'action' => $schema->string()
                ->description('The calendar action to perform: list, create, update, delete, find, ics, today, week')
                ->enum(['list', 'create', 'update', 'delete', 'find', 'ics', 'today', 'week']),
            'title' => $schema->string()
                ->description('Event title'),
            'description' => $schema->string()
                ->description('Event description'),
            'start' => $schema->string()
                ->description('Event start date/time (e.g., "2026-02-24 14:00" or "tomorrow at 3pm")'),
            'end' => $schema->string()
                ->description('Event end date/time'),
            'location' => $schema->string()
                ->description('Event location'),
            'event_id' => $schema->string()
                ->description('The event ID to update or delete'),
            'query' => $schema->string()
                ->description('Search query for finding events'),
            'limit' => $schema->integer()
                ->description('Maximum number of events to return (default: 10)'),
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
     * Get all events from storage.
     */
    protected function getEvents(): array
    {
        return Cache::get($this->cacheKey, []);
    }

    /**
     * Save events to storage.
     */
    protected function saveEvents(array $events): void
    {
        Cache::put($this->cacheKey, $events, now()->addYear());
    }

    /**
     * List events.
     */
    protected function listEvents(array $params): string
    {
        $events = $this->getEvents();
        $limit = $params['limit'] ?? 10;

        if (empty($events)) {
            return 'No events found. Use "create" action to add events.';
        }

        // Sort by start date
        usort($events, fn ($a, $b) => strtotime($a['start']) <=> strtotime($b['start']));

        // Filter to upcoming events
        $now = time();
        $upcoming = array_filter($events, fn ($e) => strtotime($e['start']) >= $now);

        $upcoming = array_slice($upcoming, 0, $limit);

        if (empty($upcoming)) {
            return 'No upcoming events found.';
        }

        $output = "Upcoming Events:\n";
        $output .= str_repeat('=', 50)."\n\n";

        foreach ($upcoming as $event) {
            $start = Carbon::parse($event['start']);
            $output .= "[{$event['id']}] {$event['title']}\n";
            $output .= "    When: {$start->format('D, M j, Y g:i A')}\n";
            if (! empty($event['location'])) {
                $output .= "    Where: {$event['location']}\n";
            }
            $output .= "\n";
        }

        return $output;
    }

    /**
     * Create a new event.
     */
    protected function createEvent(array $params): string
    {
        $title = $params['title'] ?? null;
        $start = $params['start'] ?? null;
        $end = $params['end'] ?? null;

        if (! $title || ! $start) {
            return 'Error: title and start are required for creating an event.';
        }

        $events = $this->getEvents();

        $event = [
            'id' => uniqid('evt_'),
            'title' => $title,
            'description' => $params['description'] ?? '',
            'start' => $this->parseDateTime($start),
            'end' => $end ? $this->parseDateTime($end) : null,
            'location' => $params['location'] ?? '',
            'created_at' => now()->toIso8601String(),
        ];

        $events[] = $event;
        $this->saveEvents($events);

        $formattedStart = Carbon::parse($event['start'])->format('D, M j, Y g:i A');

        return "Event created successfully!\nID: {$event['id']}\nTitle: {$title}\nStart: {$formattedStart}";
    }

    /**
     * Update an existing event.
     */
    protected function updateEvent(array $params): string
    {
        $eventId = $params['event_id'] ?? null;

        if (! $eventId) {
            return 'Error: event_id is required for updating an event.';
        }

        $events = $this->getEvents();
        $found = false;

        foreach ($events as &$event) {
            if ($event['id'] === $eventId) {
                $found = true;

                if (isset($params['title'])) {
                    $event['title'] = $params['title'];
                }
                if (isset($params['description'])) {
                    $event['description'] = $params['description'];
                }
                if (isset($params['start'])) {
                    $event['start'] = $this->parseDateTime($params['start']);
                }
                if (isset($params['end'])) {
                    $event['end'] = $this->parseDateTime($params['end']);
                }
                if (isset($params['location'])) {
                    $event['location'] = $params['location'];
                }
                $event['updated_at'] = now()->toIso8601String();

                break;
            }
        }

        if (! $found) {
            return "Event {$eventId} not found.";
        }

        $this->saveEvents($events);

        return "Event {$eventId} updated successfully.";
    }

    /**
     * Delete an event.
     */
    protected function deleteEvent(array $params): string
    {
        $eventId = $params['event_id'] ?? null;

        if (! $eventId) {
            return 'Error: event_id is required for deleting an event.';
        }

        $events = $this->getEvents();
        $initialCount = count($events);

        $events = array_filter($events, fn ($e) => $e['id'] !== $eventId);

        if (count($events) === $initialCount) {
            return "Event {$eventId} not found.";
        }

        $this->saveEvents(array_values($events));

        return "Event {$eventId} deleted successfully.";
    }

    /**
     * Find events by query.
     */
    protected function findEvents(array $params): string
    {
        $query = strtolower($params['query'] ?? '');
        $events = $this->getEvents();

        if (! $query) {
            return 'Error: query is required for searching.';
        }

        $results = array_filter($events, function ($event) use ($query) {
            return str_contains(strtolower($event['title']), $query) ||
                   str_contains(strtolower($event['description'] ?? ''), $query) ||
                   str_contains(strtolower($event['location'] ?? ''), $query);
        });

        if (empty($results)) {
            return "No events found matching '{$query}'.";
        }

        $output = "Search results for '{$query}':\n";
        $output .= str_repeat('-', 40)."\n\n";

        foreach ($results as $event) {
            $start = Carbon::parse($event['start']);
            $output .= "[{$event['id']}] {$event['title']}\n";
            $output .= "    When: {$start->format('D, M j, Y g:i A')}\n\n";
        }

        return $output;
    }

    /**
     * Generate ICS file content.
     */
    protected function generateIcs(array $params): string
    {
        $eventId = $params['event_id'] ?? null;
        $events = $this->getEvents();

        if ($eventId) {
            $events = array_filter($events, fn ($e) => $e['id'] === $eventId);
        }

        if (empty($events)) {
            return 'No events to export.';
        }

        $ics = "BEGIN:VCALENDAR\n";
        $ics .= "VERSION:2.0\n";
        $ics .= "PRODID:-//Laraclaw//Calendar//EN\n";
        $ics .= "CALSCALE:GREGORIAN\n";
        $ics .= "METHOD:PUBLISH\n";

        foreach ($events as $event) {
            $start = Carbon::parse($event['start']);
            $end = $event['end'] ? Carbon::parse($event['end']) : $start->copy()->addHour();

            $ics .= "BEGIN:VEVENT\n";
            $ics .= "UID:{$event['id']}@laraclaw\n";
            $ics .= 'DTSTAMP:'.now()->format('Ymd\THis\Z')."\n";
            $ics .= "DTSTART:{$start->format('Ymd\THis')}\n";
            $ics .= "DTEND:{$end->format('Ymd\THis')}\n";
            $ics .= "SUMMARY:{$this->escapeIcs($event['title'])}\n";

            if (! empty($event['description'])) {
                $ics .= "DESCRIPTION:{$this->escapeIcs($event['description'])}\n";
            }
            if (! empty($event['location'])) {
                $ics .= "LOCATION:{$this->escapeIcs($event['location'])}\n";
            }

            $ics .= "END:VEVENT\n";
        }

        $ics .= "END:VCALENDAR\n";

        return "ICS Calendar File:\n```\n{$ics}```";
    }

    /**
     * Get today's events.
     */
    protected function getTodayEvents(): string
    {
        $events = $this->getEvents();
        $today = Carbon::today();

        $todaysEvents = array_filter($events, function ($event) {
            $start = Carbon::parse($event['start']);

            return $start->isToday();
        });

        if (empty($todaysEvents)) {
            return 'No events scheduled for today.';
        }

        usort($todaysEvents, fn ($a, $b) => strtotime($a['start']) <=> strtotime($b['start']));

        $output = "Today's Events ({$today->format('D, M j, Y')}):\n";
        $output .= str_repeat('=', 40)."\n\n";

        foreach ($todaysEvents as $event) {
            $start = Carbon::parse($event['start']);
            $output .= "[{$event['id']}] {$start->format('g:i A')} - {$event['title']}\n";
            if (! empty($event['location'])) {
                $output .= "    Location: {$event['location']}\n";
            }
            $output .= "\n";
        }

        return $output;
    }

    /**
     * Get this week's events.
     */
    protected function getWeekEvents(): string
    {
        $events = $this->getEvents();
        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        $weekEvents = array_filter($events, function ($event) use ($startOfWeek, $endOfWeek) {
            $start = Carbon::parse($event['start']);

            return $start->between($startOfWeek, $endOfWeek);
        });

        if (empty($weekEvents)) {
            return 'No events scheduled for this week.';
        }

        usort($weekEvents, fn ($a, $b) => strtotime($a['start']) <=> strtotime($b['start']));

        $output = "This Week's Events:\n";
        $output .= str_repeat('=', 40)."\n\n";

        foreach ($weekEvents as $event) {
            $start = Carbon::parse($event['start']);
            $output .= "[{$event['id']}] {$start->format('D M j, g:i A')} - {$event['title']}\n";
            if (! empty($event['location'])) {
                $output .= "    Location: {$event['location']}\n";
            }
            $output .= "\n";
        }

        return $output;
    }

    /**
     * Parse a date/time string.
     */
    protected function parseDateTime(string $dateTime): string
    {
        try {
            // Try natural language parsing
            $parsed = Carbon::parse($dateTime);

            return $parsed->toIso8601String();
        } catch (\Exception $e) {
            // Return original if parsing fails
            return $dateTime;
        }
    }

    /**
     * Escape special characters for ICS format.
     */
    protected function escapeIcs(string $text): string
    {
        return str_replace(
            ['\\', ';', ',', "\n"],
            ['\\\\', '\\;', '\\,', '\\n'],
            $text
        );
    }
}
