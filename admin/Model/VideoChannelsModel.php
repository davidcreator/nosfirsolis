<?php

namespace Admin\Model;

class VideoChannelsModel extends AbstractCrudModel
{
    protected string $table = 'video_channels';
    protected array $fillable = ['name', 'slug', 'description', 'status'];
}
