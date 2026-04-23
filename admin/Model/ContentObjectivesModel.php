<?php

namespace Admin\Model;

class ContentObjectivesModel extends AbstractCrudModel
{
    protected string $table = 'content_objectives';
    protected array $fillable = ['name', 'slug', 'description', 'status'];
}
