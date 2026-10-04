<?php

namespace App\Services;

class ClientCategoryService
{
    public const TYPE_PHYSIQUE = 'Personne Physique';
    public const TYPE_MORALE = 'Personne Morale';

    public static function getTypes(): array
    {
        return [
            self::TYPE_PHYSIQUE => self::TYPE_PHYSIQUE,
            self::TYPE_MORALE => self::TYPE_MORALE,
        ];
    }

    public static function getCategoriesByType(): array
    {
        return [
            self::TYPE_PHYSIQUE => [
                'Salarié secteur public' => 'Salarié secteur public',
                'Salarié secteur privé' => 'Salarié secteur privé',
                'Agent des organismes internationaux' => 'Agent des organismes internationaux',
                'Profession libérale' => 'Profession libérale',
                'Commerçant / entrepreneur' => 'Commerçant / entrepreneur',
                'Chômeur' => 'Chômeur',
            ],
            self::TYPE_MORALE => [
                'Banques' => 'Banques',
                'OPC' => 'OPC',
                'Caisses de dépôt et consignation (CDC)' => 'Caisses de dépôt et consignation (CDC)',
                'Autres institutions financières' => 'Autres institutions financières',
                'Sociétés de bourse' => 'Sociétés de bourse',
                "Société de gestion d'OPC" => "Société de gestion d'OPC",
                'Entreprises non financières' => 'Entreprises non financières',
            ],
        ];
    }

    public static function getCategoriesForType(?string $type): array
    {
        $all = self::getCategoriesByType();

        if ($type === self::TYPE_MORALE) {
            return $all[self::TYPE_MORALE];
        }

        return $all[self::TYPE_PHYSIQUE];
    }

    public static function getAllCategories(): array
    {
        $byType = self::getCategoriesByType();
        return array_merge($byType[self::TYPE_PHYSIQUE], $byType[self::TYPE_MORALE]);
    }

    public static function getTypeForCategory(?string $category): string
    {
        if (empty($category)) {
            return self::TYPE_PHYSIQUE;
        }

        $moraleCategories = array_keys(self::getCategoriesByType()[self::TYPE_MORALE]);
        if (in_array($category, $moraleCategories, true) || in_array($category, ['Personne Morale', 'Institutionnel', 'Entreprise', 'Entreprises'], true)) {
            return self::TYPE_MORALE;
        }

        return self::TYPE_PHYSIQUE;
    }
}