<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasAvatar // , LogsActivity
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, LogsActivity, Notifiable;

    protected $connection = 'mysql';

    protected $orderBy = 'name';

    protected $orderDirection = 'asc';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            // Se l'utente in fase di creazione non ha una password impostata (es.
            // tramite Socialite) assegniamo una password casuale non indovinabile.
            // In passato veniva impostata la stringa fissa 'password': con il login
            // via credenziali attivo erano account con password nota.
            if (empty($user->password)) {
                $user->password = Hash::make(Str::random(40));
            }
        });
    }

    /**
     * Autorizza l'accesso al pannello Filament.
     *
     * Con la registrazione via Socialite attiva chiunque abbia un account Google
     * o Microsoft potrebbe creare un utente. Se e' configurato un elenco di domini
     * email consentiti (config panel.allowed_email_domains) l'accesso e' limitato a
     * quei domini; gli utenti gia' presenti prima dell'attivazione dell'elenco
     * restano abilitati. Elenco vuoto = comportamento storico (tutti abilitati).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        $allowedDomains = array_filter((array) config('panel.allowed_email_domains', []));

        if ($allowedDomains === []) {
            return true;
        }

        $domain = Str::lower(Str::afterLast($this->email, '@'));

        if (in_array($domain, array_map('strtolower', $allowedDomains), true)) {
            return true;
        }

        // Grandfathering: gli utenti creati prima di ora non vengono espulsi.
        return $this->wasRecentlyCreated === false
            && $this->created_at !== null
            && $this->created_at->lt(now()->subMinute());
    }

    public function getFilamentAvatarUrl(): ?string
    {
        if ($this->avatar_url) {
            return $this->avatar_url;
        }

        $socialUser = $this->socialiteUsers()->whereNotNull('avatar')->first();
        if ($socialUser) {
            return $socialUser->avatar;
        }

        return null;
    }

    public function socialiteUsers(): HasMany
    {
        return $this->hasMany(SocialiteUser::class);
    }

    /*
      public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->useLogName('user')
            ->setDescriptionForEvent(fn (string $eventName) => "Utente {$this->name} ha effettuato l'evento: {$eventName}");
    }
            */

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class, 'user_id', 'id');
    }

    /**
     * Verifica i permessi dell'utente passando per i ruoli del suo Employee.
     */
    public function hasPermission(string $resource, string $action = 'viewAny'): bool
    {
        // Il super admin non ha (e non deve avere) un profilo Employee: bypassa
        // il controllo granulare basato su Employee/EmployeeTypePermission.
        if ($this->role === UserRole::ADMIN->value) {
            return true;
        }

        // Carica la relazione se non è ancora presente
        $employee = $this->employee;

        // Se l'utente non ha un profilo Employee o non ha ruoli assegnati
        if (! $employee || empty($employee->employee_roles)) {
            return false;
        }

        // Verifica se almeno uno dei ruoli dell'impiegato ha il permesso attivo
        return EmployeeTypePermission::query()
            ->whereHas('employeeType', function ($query) use ($employee) {
                $query->whereIn('name', $employee->employee_roles);
            })
            ->where('resource', $resource)
            ->where('action', $action)
            ->exists();
    }
}
