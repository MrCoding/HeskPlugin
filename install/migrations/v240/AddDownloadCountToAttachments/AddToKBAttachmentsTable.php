<?php

namespace v240\AddDownloadCountToAttachments;


class AddToKBAttachmentsTable extends \AbstractMigration {

    function up($hesk_settings) {
        $this->executeQuery("ALTER TABLE `" . hesk_dbEscape($hesk_settings['db_pfix']) . "kb_attachments` ADD COLUMN `download_count` INT NOT NULL DEFAULT 0");
    }

    function down($hesk_settings) {
        $this->executeQuery("ALTER TABLE `" . hesk_dbEscape($hesk_settings['db_pfix']) . "kb_attachments` DROP COLUMN `download_count`");
    }
}