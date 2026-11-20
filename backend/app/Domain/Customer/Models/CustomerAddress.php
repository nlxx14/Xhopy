<?php

namespace App\Domain\Customer\Models;

use App\Models\User;
use Database\Factories\CustomerAddressFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $label
 * @property string $recipient_name
 * @property string $phone
 * @property string $line_1
 * @property string|null $line_2
 * @property string|null $barangay
 * @property string $city
 * @property string $province
 * @property string $postal_code
 * @property string $country_code
 * @property string|null $delivery_notes
 * @property bool $is_default_shipping
 * @property bool $is_default_billing
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[UseFactory(CustomerAddressFactory::class)]
class CustomerAddress extends Model
{
    /** @use HasFactory<CustomerAddressFactory> */
    use HasFactory;

    public const MAX_ADDRESSES_PER_USER = 10;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'label',
        'recipient_name',
        'phone',
        'line_1',
        'line_2',
        'barangay',
        'city',
        'province',
        'postal_code',
        'country_code',
        'delivery_notes',
        'is_default_shipping',
        'is_default_billing',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default_shipping' => 'boolean',
            'is_default_billing' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toFrontendArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'recipientName' => $this->recipient_name,
            'phone' => $this->phone,
            'line1' => $this->line_1,
            'line2' => $this->line_2,
            'barangay' => $this->barangay,
            'city' => $this->city,
            'province' => $this->province,
            'postalCode' => $this->postal_code,
            'countryCode' => $this->country_code,
            'deliveryNotes' => $this->delivery_notes,
            'isDefaultShipping' => $this->is_default_shipping,
            'isDefaultBilling' => $this->is_default_billing,
            'formattedSummary' => implode(', ', array_filter([
                $this->line_1,
                $this->line_2,
                $this->barangay,
                $this->city,
                $this->province.' '.$this->postal_code,
            ])),
        ];
    }
}
