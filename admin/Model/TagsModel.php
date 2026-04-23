<?php

namespace Admin\Model;

class TagsModel extends AbstractCrudModel
{
    protected string $table = 'tags';
    protected array $fillable = ['name', 'slug', 'status'];
}
