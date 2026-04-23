<?php

namespace Admin\Model;

class ContentPlatformsModel extends AbstractCrudModel
{
    protected string $table = 'content_platforms';
    protected array $fillable = ['name', 'slug', 'platform_type', 'source', 'status'];

    public function groupedByType(): array
    {
        $rows = $this->all([], 'platform_type ASC, name ASC');
        $grouped = [];

        foreach ($rows as $row) {
            $grouped[$row['platform_type']][] = $row;
        }

        return $grouped;
    }
}
