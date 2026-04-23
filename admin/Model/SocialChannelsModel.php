<?php

namespace Admin\Model;

class SocialChannelsModel extends AbstractCrudModel
{
    protected string $table = 'social_channels';
    protected array $fillable = ['name', 'slug', 'description', 'status'];
}
