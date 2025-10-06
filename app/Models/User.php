<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'address_line_1',
        'address_line_2',
        'address_line_3',
        'city',
        'state',
        'postal_code',
        'country',
        'bio',
        'avatar',
        'user_type_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Get the user type for the user.
     */
    public function userType()
    {
        return $this->belongsTo(UserType::class);
    }

    /**
     * Check if user is of a specific type.
     */
    public function isType(string $typeName): bool
    {
        return $this->userType && $this->userType->name === $typeName;
    }

    public function getJWTIdentifier()
    {
        return $this->getKey(); // Returns user ID
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
}
