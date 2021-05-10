<?php

namespace DataAccess\Categories;


use BusinessLogic\Categories\CategoryGroup;
use DataAccess\CommonDao;

class CategoryGroupGateway extends CommonDao {
    public function getAllCategoryGroups($heskSettings) {
        $this->init();

        $sql = "SELECT `cat_group`.*, `i18n`.`language`, `i18n`.`text`, COUNT(`cat`.`id`) AS `number_of_categories`
            FROM `" . hesk_dbEscape($heskSettings['db_pfix']) . "mfh_category_groups` `cat_group`
            INNER JOIN `" . hesk_dbEscape($heskSettings['db_pfix']) . "mfh_category_groups_i18n` `i18n`
                ON `cat_group`.`id` = `i18n`.`category_group_id`
            LEFT JOIN `" . hesk_dbEscape($heskSettings['db_pfix']) . "categories` `cat`
                ON `cat_group`.`id` = `cat`.`mfh_category_group_id`
            GROUP BY `cat_group`.`id`, `i18n`.`language`, `i18n`.`text`
            ORDER BY `cat_group`.`sort`, `cat_group`.`id` ASC";

        $rs = hesk_dbQuery($sql);

        $categoryGroups = array();
        $lastId = -1;
        $categoryGroup = null;
        while ($row = hesk_dbFetchAssoc($rs)) {
            if ($lastId === -1 || $lastId !== $row['id']) {
                if ($categoryGroup !== null) {
                    $categoryGroups[] = $categoryGroup;
                }
                $categoryGroup = new CategoryGroup();
                $categoryGroup->id = $row['id'];
                $categoryGroup->parentId = $row['parent_id'];
                $categoryGroup->numberOfCategories = $row['number_of_categories'];
                $categoryGroup->sort = $row['sort'];
                $categoryGroup->names = array();
            }

            $categoryGroup->names[$row['language']] = $row['text'];

            $lastId = $row['id'];
        }
        if ($categoryGroup !== null) {
            $categoryGroups[] = $categoryGroup;
        }

        return $categoryGroups;
    }

    public function doesCategoryGroupExist($id, $heskSettings) {
        $this->init();

        $rs = hesk_dbQuery("SELECT 1 FROM `" . hesk_dbEscape($heskSettings['db_pfix']) . "mfh_category_groups` WHERE `id` = " . intval($id));

        $result = false;
        if (hesk_dbFetchAssoc($rs)) {
           $result = true;
        }

        $this->close();

        return $result;
    }

    public function createCategoryGroup($heskSettings, CategoryGroup $categoryGroup) {
        $this->init();

        $parentId = $categoryGroup->parentId === null ? "NULL" : intval($categoryGroup->parentId);
        $newOrderRs = hesk_dbQuery("SELECT `sort` FROM `" . hesk_dbEscape($heskSettings['db_pfix']) . "mfh_category_groups` ORDER BY `sort` DESC LIMIT 1");
        $newOrder = hesk_dbFetchAssoc($newOrderRs);

        $newSort = $categoryGroup->sort === null ? intval($newOrder['sort']) + 10 : intval($categoryGroup->sort);
        $sql = "INSERT INTO `" . hesk_dbEscape($heskSettings['db_pfix']) . "mfh_category_groups` (`parent_id`, `sort`)
            VALUES ({$parentId}, {$newSort})";
        hesk_dbQuery($sql);

        $id = hesk_dbInsertID();

        // i18n
        foreach ($categoryGroup->names as $language => $name) {
            $sql = "INSERT INTO `" . hesk_dbEscape($heskSettings['db_pfix']) . "mfh_category_groups_i18n` (`category_group_id`, `language`, `text`)
                VALUES ({$id}, '" . hesk_dbEscape($language) . "', '" . hesk_dbEscape($name) . "')";
            hesk_dbQuery($sql);
        }

        $this->close();

        $categoryGroup->id = $id;
        $categoryGroup->sort = $newSort;
        $categoryGroup->numberOfCategories = 0;

        return $categoryGroup;
    }

    public function updateCategorySortAndParent($id, $sort, $parent, $heskSettings) {
        $this->init();

        $parentString = $parent === null ? 'NULL' : intval($parent);

        hesk_dbQuery("UPDATE `" . hesk_dbEscape($heskSettings['db_pfix']) . "mfh_category_groups`
            SET `parent_id` = {$parentString}, `sort` = {$sort} WHERE `id` = {$id}");

        $this->close();
    }

    public function moveCategoriesToParentsParent($id, $heskSettings) {
        $this->init();

        $parentRs = hesk_dbQuery("SELECT `parent_id` FROM `" . hesk_dbEscape($heskSettings['db_pfix']) . "mfh_category_groups`
            WHERE `id` = " . intval($id));

        $newParent = 'NULL';
        if ($row = hesk_dbFetchAssoc($parentRs)) {
            $newParent = intval($row['parent_id']) === 0 ? 'NULL' : intval($row['parent_id']);
        }

        hesk_dbQuery("UPDATE `" . hesk_dbEscape($heskSettings['db_pfix']) . "mfh_category_groups`
            SET `parent_id` = " . $newParent . "
            WHERE `parent_id` = " . intval($id));

        $this->close();
    }

    public function updateCategoryGroup($heskSettings, CategoryGroup $categoryGroup) {
        $this->init();

        $parentId = $categoryGroup->parentId === null ? 'NULL' : intval($categoryGroup->parentId);
        $id = intval($categoryGroup->id);

        hesk_dbQuery("UPDATE `" . hesk_dbEscape($heskSettings['db_pfix']) . "mfh_category_groups`
            SET `parent_id` = " . $parentId . ",
                `sort` = " . intval($categoryGroup->sort) . "
            WHERE `id` = " . $id);

        hesk_dbQuery("DELETE FROM `" . hesk_dbEscape($heskSettings['db_pfix']) . "mfh_category_groups_i18n`
            WHERE `category_group_id` = " . $id);
        foreach ($categoryGroup->names as $language => $name) {
            $sql = "INSERT INTO `" . hesk_dbEscape($heskSettings['db_pfix']) . "mfh_category_groups_i18n` (`category_group_id`, `language`, `text`)
                VALUES (" . $id . ", '" . hesk_dbEscape($language) . "', '" . hesk_dbEscape($name) . "')";
            hesk_dbQuery($sql);
        }

        $this->close();

        return $categoryGroup;
    }

    public function deleteCategoryGroup($id, $heskSettings) {
        $this->init();

        $parentId = null;
        $parentRs = hesk_dbQuery("SELECT `parent_id` FROM `" . hesk_dbEscape($heskSettings['db_pfix']) . "mfh_category_groups`
            WHERE `id` = " . intval($id));
        if ($row = hesk_dbFetchAssoc($parentRs)) {
            $parentId = $row['parent_id'] === null ? 'NULL' : $row['parent_id'];
        }

        hesk_dbQuery("DELETE FROM `" . hesk_dbEscape($heskSettings['db_pfix']) . "mfh_category_groups_i18n`
            WHERE `category_group_id` = " . intval($id));
        hesk_dbQuery("DELETE FROM `" . hesk_dbEscape($heskSettings['db_pfix']) . "mfh_category_groups`
            WHERE `id` = " . intval($id));

        // Any categories in this group will be moved to the "null" category group or its parent
        hesk_dbQuery("UPDATE `" . hesk_dbEscape($heskSettings['db_pfix']) . "categories`
            SET `mfh_category_group_id` = {$parentId}
            WHERE `mfh_category_group_id` = " . intval($id));

        $this->close();
    }
}