<?php

namespace Admin\Model;

class ContentPillarsModel extends AbstractCrudModel
{
    protected string $table = 'content_pillars';
    protected array $fillable = ['name', 'slug', 'description', 'status'];
}
