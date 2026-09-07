<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Wedding extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'slug',
        'bride_name',
        'groom_name',
        'bride_parents',
        'groom_parents',
        'wedding_date',
        'wedding_time',
        'venue_name',
        'venue_address',
        'venue_map_url',
        'bride_photo_id',
        'groom_photo_id',
        'couple_photo_id',
        'custom_content',
        'sections_config',
        'applied_template_id',
        'is_premium_unlocked',
        'rsvp_enabled',
        'rsvp_deadline',
        'wishes_enabled',
        'rsvp_notification_enabled',
        'rsvp_notification_email',
        'status',
        'published_at',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array
     */
    protected $attributes = [
        'status' => 'draft',
        'rsvp_enabled' => true,
        'wishes_enabled' => true,
        'rsvp_notification_enabled' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'wedding_date' => 'date:Y-m-d',
            'rsvp_deadline' => 'date:Y-m-d',
            'custom_content' => 'array',
            'sections_config' => 'array',
            'is_premium_unlocked' => 'boolean',
            'rsvp_enabled' => 'boolean',
            'wishes_enabled' => 'boolean',
            'rsvp_notification_enabled' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Applied template relationship.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'applied_template_id');
    }

    /**
     * Owner user relationship.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Associated design schema and published snapshots.
     */
    public function design(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Design::class);
    }

    /**
     * Guest groups for this wedding.
     */
    public function guestGroups(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(GuestGroup::class);
    }

    /**
     * Guests invited to this wedding.
     */
    public function guests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Guest::class);
    }

    /**
     * Invitations generated for guests of this wedding.
     */
    public function invitations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /**
     * RSVPs submitted for this wedding.
     */
    public function rsvps(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Rsvp::class);
    }

    /**
     * Page views recorded for this wedding.
     */
    public function pageViews(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PageView::class);
    }

    /**
     * Template purchases / unlocks for this specific wedding.
     */
    public function templatePurchases(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(WeddingTemplatePurchase::class);
    }

    /**
     * Unlocked templates for this wedding.
     */
    public function unlockedTemplates(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Template::class, 'wedding_template_purchases')
            ->withPivot(['payment_transaction_id', 'unlocked_at'])
            ->withTimestamps();
    }

    /**
     * Check if a specific template is unlocked/licensed for this specific wedding.
     */
    public function isTemplateUnlocked(int|Template|null $template, ?User $user = null): bool
    {
        if (!$template) {
            return true;
        }

        $templateModel = is_numeric($template) ? Template::find($template) : $template;
        if (!$templateModel) {
            return true;
        }

        // Free templates are always accessible
        if ((int) $templateModel->price <= 0) {
            return true;
        }

        // Platform admins have universal access
        if ($user && method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        // Check if explicitly unlocked for this wedding in wedding_template_purchases
        $hasPurchase = $this->templatePurchases()
            ->where('template_id', $templateModel->id)
            ->exists();

        if ($hasPurchase) {
            return true;
        }

        // Also check if there's a paid transaction for this wedding and template
        $hasPaidTransaction = PaymentTransaction::where('wedding_id', $this->id)
            ->where('template_id', $templateModel->id)
            ->where('status', 'paid')
            ->exists();

        if ($hasPaidTransaction) {
            // Auto-heal purchase record
            $this->unlockTemplate($templateModel);
            return true;
        }

        return false;
    }

    /**
     * Unlock a specific template for this wedding.
     */
    public function unlockTemplate(int|Template $template, ?int $transactionId = null): WeddingTemplatePurchase
    {
        $templateId = is_numeric($template) ? (int) $template : $template->id;

        $purchase = WeddingTemplatePurchase::firstOrCreate(
            [
                'wedding_id' => $this->id,
                'template_id' => $templateId,
            ],
            [
                'payment_transaction_id' => $transactionId,
                'unlocked_at' => now(),
            ]
        );

        if ($transactionId && !$purchase->payment_transaction_id) {
            $purchase->update(['payment_transaction_id' => $transactionId]);
        }

        // If this is the currently applied template, sync the boolean flag as well
        if ((int) $this->applied_template_id === $templateId) {
            $this->updateQuietly(['is_premium_unlocked' => true]);
        }

        return $purchase;
    }
}

