<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** Eigen indeling van games per venue (Party, Kids, Racing, ...). Volgorde = volgorde van de filters in Playscope. */
class GameCategory extends Model
{
    protected $fillable = ['tenant_id', 'name', 'color', 'sort_order'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'game_game_category');
    }

    public function toPublicArray(): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'color'      => $this->color,
            'sort_order' => (int) $this->sort_order,
        ];
    }
}
