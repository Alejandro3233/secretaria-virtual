<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\CarbonInterface;

class Stylist extends Model
{
    protected $fillable = [
        'clinic_id',
        'service_id',
        'name',
        'email',
        'avatar_path',
        'phone',
        'specialty',
        'work_days',
        'work_starts_at',
        'work_ends_at',
        'break_starts_at',
        'break_ends_at',
        'weekly_schedule',
        'is_active',
        'is_internal',
    ];

    protected $casts = [
        'work_days' => 'array',
        'weekly_schedule' => 'array',
        'is_active' => 'boolean',
        'is_internal' => 'boolean',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class)->withTimestamps();
    }

    public function canPerformService(?int $serviceId): bool
    {
        if (! $serviceId) {
            return true;
        }

        if ((int) $this->service_id === $serviceId) {
            return true;
        }

        return $this->relationLoaded('services')
            ? $this->services->contains('id', $serviceId)
            : $this->services()->whereKey($serviceId)->exists();
    }

    public function isOnBreak(CarbonInterface $startsAt, CarbonInterface $endsAt): bool
    {
        $schedule = $this->scheduleForDate($startsAt);
        if (! $schedule || empty($schedule['break_start']) || empty($schedule['break_end'])) {
            return false;
        }

        [$startHour, $startMinute] = array_pad(array_map('intval', explode(':', $schedule['break_start'])), 2, 0);
        [$endHour, $endMinute] = array_pad(array_map('intval', explode(':', $schedule['break_end'])), 2, 0);
        $breakStart = $startsAt->copy()->setTime($startHour, $startMinute);
        $breakEnd = $startsAt->copy()->setTime($endHour, $endMinute);

        return $startsAt->lessThan($breakEnd) && $endsAt->greaterThan($breakStart);
    }

    public function scheduleForDate(CarbonInterface $date): ?array
    {
        $day = strtolower($date->englishDayOfWeek);
        if (is_array($this->weekly_schedule) && $this->weekly_schedule !== []) {
            $schedule = $this->weekly_schedule[$day] ?? [];
            if (empty($schedule['enabled'])) return null;

            return [
                'start' => $schedule['start'] ?? '08:00', 'end' => $schedule['end'] ?? '21:00',
                'break_start' => $schedule['break_start'] ?? null, 'break_end' => $schedule['break_end'] ?? null,
            ];
        }

        $workDays = $this->work_days ?: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        if (! in_array($day, $workDays, true)) return null;

        return [
            'start' => $this->work_starts_at ?: '08:00', 'end' => $this->work_ends_at ?: '21:00',
            'break_start' => $this->break_starts_at, 'break_end' => $this->break_ends_at,
        ];
    }

    public function worksOnDate(CarbonInterface $date): bool
    {
        return $this->scheduleForDate($date) !== null;
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)) ?: [])->filter()->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('') ?: 'PE';
    }

    public function avatarUrl(): ?string
    {
        if (! $this->avatar_path) return null;
        if (str_starts_with($this->avatar_path, 'preset:')) {
            return asset('images/staff-avatars/'.substr($this->avatar_path, 7));
        }

        return asset('storage/'.$this->avatar_path);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function vacations(): HasMany
    {
        return $this->hasMany(StylistVacation::class);
    }
}
