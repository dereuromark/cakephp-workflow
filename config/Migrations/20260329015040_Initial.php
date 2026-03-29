<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class Initial extends BaseMigration
{
    /**
     * Up Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/5/en/migrations.html#the-up-method
     *
     * @return void
     */
    public function up(): void
    {
        $this->table('audit_logs')
            ->addColumn('transaction', 'binaryuuid', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('type', 'string', [
                'default' => null,
                'limit' => 7,
                'null' => false,
            ])
            ->addColumn('primary_key', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'signed' => false,
            ])
            ->addColumn('display_value', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('source', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('parent_source', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('user_id', 'string', [
                'default' => null,
                'limit' => 36,
                'null' => true,
            ])
            ->addColumn('user_display', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('original', 'text', [
                'default' => null,
                'limit' => 16777215,
                'null' => true,
            ])
            ->addColumn('changed', 'text', [
                'default' => null,
                'limit' => 16777215,
                'null' => true,
            ])
            ->addColumn('meta', 'text', [
                'default' => null,
                'limit' => 16777215,
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addIndex(
                $this->index('transaction')
                    ->setName('transaction')
            )
            ->addIndex(
                $this->index('type')
                    ->setName('type')
            )
            ->addIndex(
                $this->index('primary_key')
                    ->setName('primary_key')
            )
            ->addIndex(
                $this->index('display_value')
                    ->setName('display_value')
            )
            ->addIndex(
                $this->index('source')
                    ->setName('source')
            )
            ->addIndex(
                $this->index('parent_source')
                    ->setName('parent_source')
            )
            ->addIndex(
                $this->index('user_display')
                    ->setName('username')
            )
            ->addIndex(
                $this->index('created')
                    ->setName('created')
            )
            ->addIndex(
                $this->index('user_id')
                    ->setName('user_id')
            )
            ->create();

        $this->table('bitmasked_records')
            ->addColumn('name', 'string', [
                'default' => null,
                'limit' => 100,
                'null' => false,
            ])
            ->addColumn('flag_optional', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('flag_required', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->create();

        $this->table('bouncer_records')
            ->addColumn('source', 'string', [
                'comment' => 'Table name (e.g., Articles)',
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('primary_key', 'integer', [
                'comment' => 'ID of record in source table, NULL for new records',
                'default' => null,
                'limit' => null,
                'null' => true,
                'signed' => false,
            ])
            ->addColumn('user_id', 'integer', [
                'comment' => 'User who proposed the change',
                'default' => null,
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('user_display', 'string', [
                'comment' => 'Display name for user (optional)',
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('reviewer_id', 'integer', [
                'comment' => 'Admin who approved/rejected',
                'default' => null,
                'limit' => null,
                'null' => true,
                'signed' => false,
            ])
            ->addColumn('reviewer_display', 'string', [
                'comment' => 'Display name for reviewer (optional)',
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('status', 'string', [
                'comment' => 'pending, approved, rejected, superseded',
                'default' => 'pending',
                'limit' => 20,
                'null' => false,
            ])
            ->addColumn('data', 'text', [
                'comment' => 'JSON serialized entity data',
                'default' => null,
                'limit' => 16777215,
                'null' => false,
            ])
            ->addColumn('original_data', 'text', [
                'comment' => 'JSON serialized original data for edits',
                'default' => null,
                'limit' => 16777215,
                'null' => true,
            ])
            ->addColumn('original_modified', 'datetime', [
                'comment' => 'Timestamp of source record when draft was created',
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('note', 'string', [
                'comment' => 'User note explaining the reason for the change',
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('reason', 'text', [
                'comment' => 'Approval/rejection reason',
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('reviewed', 'datetime', [
                'comment' => 'When approved/rejected',
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addIndex(
                $this->index('source')
                    ->setName('source')
            )
            ->addIndex(
                $this->index('primary_key')
                    ->setName('primary_key')
            )
            ->addIndex(
                $this->index('user_id')
                    ->setName('user_id')
            )
            ->addIndex(
                $this->index('reviewer_id')
                    ->setName('reviewer_id')
            )
            ->addIndex(
                $this->index('status')
                    ->setName('status')
            )
            ->addIndex(
                $this->index('created')
                    ->setName('created')
            )
            ->addIndex(
                $this->index([
                        'source',
                        'primary_key',
                        'status',
                    ])
                    ->setName('source_2')
            )
            ->create();

        $this->table('cake_seeds')
            ->addColumn('plugin', 'string', [
                'default' => null,
                'limit' => 100,
                'null' => true,
            ])
            ->addColumn('seed_name', 'string', [
                'default' => null,
                'limit' => 100,
                'null' => false,
            ])
            ->addColumn('executed_at', 'timestamp', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->create();

        $this->table('captchas')
            ->addColumn('session_id', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('ip', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('image', 'binary', [
                'default' => null,
                'limit' => 16777215,
                'null' => true,
            ])
            ->addColumn('result', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('used', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->create();

        $this->table('comments_comments')
            ->addColumn('foreign_key', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('model', 'string', [
                'default' => null,
                'limit' => 80,
                'null' => false,
            ])
            ->addColumn('user_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'signed' => false,
            ])
            ->addColumn('parent_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'signed' => false,
            ])
            ->addColumn('name', 'string', [
                'default' => null,
                'limit' => 40,
                'null' => true,
            ])
            ->addColumn('email', 'string', [
                'default' => null,
                'limit' => 80,
                'null' => true,
            ])
            ->addColumn('title', 'string', [
                'default' => null,
                'limit' => 80,
                'null' => true,
            ])
            ->addColumn('content', 'text', [
                'default' => null,
                'limit' => 16777215,
                'null' => false,
            ])
            ->addColumn('is_private', 'boolean', [
                'default' => false,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('is_spam', 'boolean', [
                'default' => false,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addIndex(
                $this->index('user_id')
                    ->setName('comments-parent_id')
            )
            ->addIndex(
                $this->index([
                        'model',
                        'foreign_key',
                    ])
                    ->setName('comments-foreign_key')
            )
            ->create();

        $this->table('continents')
            ->addColumn('name', 'string', [
                'default' => null,
                'limit' => 64,
                'null' => false,
            ])
            ->addColumn('ori_name', 'string', [
                'default' => null,
                'limit' => 64,
                'null' => false,
            ])
            ->addColumn('parent_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'signed' => false,
            ])
            ->addColumn('lft', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'signed' => false,
            ])
            ->addColumn('rght', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'signed' => false,
            ])
            ->addColumn('status', 'tinyinteger', [
                'default' => '0',
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('code', 'string', [
                'default' => null,
                'limit' => 2,
                'null' => true,
            ])
            ->create();

        $this->table('countries')
            ->addColumn('name', 'string', [
                'default' => null,
                'limit' => 64,
                'null' => false,
            ])
            ->addColumn('ori_name', 'string', [
                'default' => null,
                'limit' => 64,
                'null' => false,
            ])
            ->addColumn('iso2', 'string', [
                'default' => null,
                'limit' => 2,
                'null' => false,
            ])
            ->addColumn('iso3', 'string', [
                'default' => null,
                'limit' => 3,
                'null' => false,
            ])
            ->addColumn('eu_member', 'boolean', [
                'comment' => 'Member of the EU',
                'default' => false,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('special', 'string', [
                'default' => null,
                'limit' => 40,
                'null' => false,
            ])
            ->addColumn('zip_length', 'tinyinteger', [
                'comment' => 'if > 0 validate on this length',
                'default' => '0',
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('zip_regexp', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('sort', 'integer', [
                'default' => '0',
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('lat', 'float', [
                'comment' => 'latitude',
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('lng', 'float', [
                'comment' => 'longitude',
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('address_format', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('status', 'tinyinteger', [
                'default' => '0',
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('timezone', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('phone_code', 'string', [
                'default' => null,
                'limit' => 20,
                'null' => true,
            ])
            ->create();

        $this->table('currencies')
            ->addColumn('name', 'string', [
                'default' => '',
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('code', 'char', [
                'default' => '',
                'limit' => 3,
                'null' => false,
            ])
            ->addColumn('symbol_left', 'string', [
                'default' => '',
                'limit' => 12,
                'null' => true,
            ])
            ->addColumn('symbol_right', 'string', [
                'default' => '',
                'limit' => 12,
                'null' => true,
            ])
            ->addColumn('decimal_places', 'char', [
                'default' => '',
                'limit' => 1,
                'null' => true,
            ])
            ->addColumn('value', 'decimal', [
                'default' => '0.0000',
                'null' => true,
                'precision' => 9,
                'scale' => 4,
            ])
            ->addColumn('base', 'boolean', [
                'comment' => 'is base currency',
                'default' => false,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('active', 'boolean', [
                'default' => false,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->create();

        $this->table('database_logs')
            ->addColumn('type', 'string', [
                'default' => null,
                'limit' => 50,
                'null' => false,
            ])
            ->addColumn('summary', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('message', 'text', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('context', 'text', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('ip', 'string', [
                'default' => null,
                'limit' => 100,
                'null' => true,
            ])
            ->addColumn('hostname', 'string', [
                'default' => null,
                'limit' => 100,
                'null' => true,
            ])
            ->addColumn('uri', 'text', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('refer', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('user_agent', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('count', 'integer', [
                'default' => '0',
                'limit' => null,
                'null' => false,
            ])
            ->create();

        $this->table('demo_articles')
            ->addColumn('title', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('content', 'text', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('status', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->create();

        $this->table('demo_articles_translations', ['id' => false, 'primary_key' => ['id', 'locale']])
            ->addColumn('id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('locale', 'string', [
                'default' => null,
                'limit' => 6,
                'null' => false,
            ])
            ->addColumn('title', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('content', 'text', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->create();

        $this->table('events')
            ->addColumn('title', 'string', [
                'default' => null,
                'limit' => 200,
                'null' => false,
            ])
            ->addColumn('location', 'string', [
                'default' => null,
                'limit' => 200,
                'null' => false,
            ])
            ->addColumn('lat', 'float', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('lng', 'float', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('description', 'text', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('beginning', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('end', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->create();

        $this->table('exposed_users')
            ->addColumn('name', 'string', [
                'default' => null,
                'limit' => 100,
                'null' => false,
            ])
            ->addColumn('uuid', 'binaryuuid', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addIndex(
                $this->index('uuid')
                    ->setName('uuid')
                    ->setType('unique')
            )
            ->create();

        $this->table('favorites_favorites')
            ->addColumn('foreign_key', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('model', 'string', [
                'default' => null,
                'limit' => 80,
                'null' => false,
            ])
            ->addColumn('user_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('value', 'tinyinteger', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addIndex(
                $this->index([
                        'model',
                        'foreign_key',
                        'user_id',
                        'value',
                    ])
                    ->setName('favorite_unique')
                    ->setType('unique')
            )
            ->addIndex(
                $this->index('user_id')
                    ->setName('favorite_user_id')
            )
            ->addIndex(
                $this->index([
                        'model',
                        'foreign_key',
                    ])
                    ->setName('favorite_foreign_key')
            )
            ->create();

        $this->table('file_storage', ['id' => false, 'primary_key' => ['id']])
            ->addColumn('id', 'uuid', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('user_id', 'uuid', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('foreign_key', 'uuid', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('model', 'string', [
                'default' => null,
                'limit' => 128,
                'null' => true,
            ])
            ->addColumn('filename', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('filesize', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('mime_type', 'string', [
                'default' => null,
                'limit' => 128,
                'null' => true,
            ])
            ->addColumn('extension', 'string', [
                'default' => null,
                'limit' => 32,
                'null' => true,
            ])
            ->addColumn('hash', 'string', [
                'default' => null,
                'limit' => 64,
                'null' => true,
            ])
            ->addColumn('path', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('adapter', 'string', [
                'default' => null,
                'limit' => 32,
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('variants', 'text', [
                'collation' => 'utf8mb4_bin',
                'default' => null,
                'limit' => 2147483647,
                'null' => true,
            ])
            ->addColumn('metadata', 'text', [
                'collation' => 'utf8mb4_bin',
                'default' => null,
                'limit' => 2147483647,
                'null' => true,
            ])
            ->addColumn('collection', 'string', [
                'default' => null,
                'limit' => 128,
                'null' => true,
            ])
            ->create();

        $this->table('languages')
            ->addColumn('name', 'string', [
                'default' => null,
                'limit' => 40,
                'null' => false,
            ])
            ->addColumn('ori_name', 'string', [
                'default' => null,
                'limit' => 40,
                'null' => false,
            ])
            ->addColumn('code', 'string', [
                'default' => null,
                'limit' => 6,
                'null' => false,
            ])
            ->addColumn('iso3', 'char', [
                'default' => null,
                'limit' => 3,
                'null' => false,
            ])
            ->addColumn('iso2', 'char', [
                'default' => null,
                'limit' => 2,
                'null' => false,
            ])
            ->addColumn('locale', 'string', [
                'default' => null,
                'limit' => 30,
                'null' => false,
            ])
            ->addColumn('locale_fallback', 'string', [
                'default' => null,
                'limit' => 30,
                'null' => false,
            ])
            ->addColumn('status', 'tinyinteger', [
                'default' => '0',
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('sort', 'integer', [
                'default' => '0',
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->create();

        $this->table('queue_processes')
            ->addColumn('pid', 'string', [
                'default' => null,
                'limit' => 40,
                'null' => false,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('terminate', 'boolean', [
                'default' => false,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('server', 'string', [
                'default' => null,
                'limit' => 90,
                'null' => true,
            ])
            ->addColumn('workerkey', 'string', [
                'default' => null,
                'limit' => 45,
                'null' => false,
            ])
            ->addIndex(
                $this->index('workerkey')
                    ->setName('workerkey')
                    ->setType('unique')
            )
            ->addIndex(
                $this->index([
                        'pid',
                        'server',
                    ])
                    ->setName('pid')
                    ->setType('unique')
            )
            ->create();

        $this->table('queue_scheduler_rows')
            ->addColumn('name', 'string', [
                'default' => null,
                'limit' => 140,
                'null' => false,
            ])
            ->addColumn('type', 'tinyinteger', [
                'default' => '0',
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('content', 'text', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('param', 'text', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('frequency', 'string', [
                'default' => null,
                'limit' => 140,
                'null' => false,
            ])
            ->addColumn('last_run', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('next_run', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('allow_concurrent', 'boolean', [
                'default' => false,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('enabled', 'boolean', [
                'default' => false,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->create();

        $this->table('queued_jobs')
            ->addColumn('job_task', 'string', [
                'default' => null,
                'limit' => 90,
                'null' => false,
            ])
            ->addColumn('data', 'text', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('job_group', 'string', [
                'default' => null,
                'limit' => 190,
                'null' => true,
            ])
            ->addColumn('reference', 'string', [
                'default' => null,
                'limit' => 190,
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('notbefore', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('fetched', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('completed', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('progress', 'float', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'signed' => false,
            ])
            ->addColumn('attempts', 'tinyinteger', [
                'default' => '0',
                'limit' => null,
                'null' => true,
                'signed' => false,
            ])
            ->addColumn('failure_message', 'text', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('workerkey', 'string', [
                'default' => null,
                'limit' => 45,
                'null' => true,
            ])
            ->addColumn('status', 'string', [
                'default' => null,
                'limit' => 190,
                'null' => true,
            ])
            ->addColumn('priority', 'integer', [
                'default' => '5',
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('memory', 'integer', [
                'comment' => 'MB',
                'default' => null,
                'limit' => null,
                'null' => true,
                'signed' => false,
            ])
            ->addIndex(
                $this->index('completed')
                    ->setName('completed')
            )
            ->addIndex(
                $this->index('job_task')
                    ->setName('job_task')
            )
            ->addIndex(
                $this->index([
                        'completed',
                        'priority',
                        'notbefore',
                        'id',
                    ])
                    ->setName('queue_fetch_optimization')
            )
            ->addIndex(
                $this->index('fetched')
                    ->setName('fetched')
            )
            ->addIndex(
                $this->index([
                        'job_task',
                        'completed',
                    ])
                    ->setName('job_task_completed')
            )
            ->addIndex(
                $this->index('workerkey')
                    ->setName('queued_jobs_workerkey')
            )
            ->create();

        $this->table('registrations')
            ->addColumn('session_id', 'string', [
                'default' => null,
                'limit' => 100,
                'null' => false,
            ])
            ->addColumn('user_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('status', 'string', [
                'default' => 'pending',
                'limit' => 100,
                'null' => false,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->create();

        $this->table('roles')
            ->addColumn('name', 'string', [
                'default' => '',
                'limit' => 64,
                'null' => false,
            ])
            ->addColumn('alias', 'string', [
                'default' => null,
                'limit' => 20,
                'null' => false,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->create();

        $this->table('sandbox_animals')
            ->addColumn('name', 'string', [
                'default' => null,
                'limit' => 100,
                'null' => false,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->create();

        $this->table('sandbox_articles')
            ->addColumn('title', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('content', 'text', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('status', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('user_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->create();

        $this->table('sandbox_categories')
            ->addColumn('parent_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('name', 'string', [
                'default' => null,
                'limit' => 180,
                'null' => false,
            ])
            ->addColumn('description', 'text', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('status', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'signed' => false,
            ])
            ->addColumn('lft', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'signed' => false,
            ])
            ->addColumn('rght', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'signed' => false,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->create();

        $this->table('sandbox_cities')
            ->addColumn('name', 'string', [
                'default' => null,
                'limit' => 190,
                'null' => false,
            ])
            ->addColumn('alias', 'string', [
                'default' => null,
                'limit' => 190,
                'null' => true,
            ])
            ->addColumn('country_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('lat', 'float', [
                'comment' => 'latitude',
                'default' => null,
                'null' => true,
                'precision' => 10,
                'scale' => 6,
            ])
            ->addColumn('lng', 'float', [
                'comment' => 'longitude',
                'default' => null,
                'null' => true,
                'precision' => 10,
                'scale' => 6,
            ])
            ->addIndex(
                $this->index([
                        'name',
                        'country_id',
                    ])
                    ->setName('name')
                    ->setType('unique')
            )
            ->addIndex(
                $this->index([
                        'lat',
                        'lng',
                    ])
                    ->setName('lat')
            )
            ->addIndex(
                $this->index('name')
                    ->setName('name_2')
            )
            ->create();

        $this->table('sandbox_posts')
            ->addColumn('title', 'string', [
                'default' => null,
                'limit' => 180,
                'null' => false,
            ])
            ->addColumn('content', 'text', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('rating_count', 'integer', [
                'default' => '0',
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('rating_sum', 'integer', [
                'default' => '0',
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->create();

        $this->table('sandbox_products')
            ->addColumn('title', 'string', [
                'default' => null,
                'limit' => 100,
                'null' => false,
            ])
            ->addColumn('price', 'decimal', [
                'default' => null,
                'null' => false,
                'precision' => 10,
                'scale' => 2,
            ])
            ->addColumn('created', 'datetime', [
                'default' => 'CURRENT_TIMESTAMP',
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => 'CURRENT_TIMESTAMP',
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('stock', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->create();

        $this->table('sandbox_profiles')
            ->addColumn('username', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('balance', 'decimal', [
                'default' => '0.00',
                'null' => false,
                'precision' => 10,
                'scale' => 2,
            ])
            ->addColumn('extra', 'decimal', [
                'default' => null,
                'null' => true,
                'precision' => 10,
                'scale' => 2,
            ])
            ->create();

        $this->table('sandbox_ratings')
            ->addColumn('user_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'signed' => false,
            ])
            ->addColumn('foreign_key', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('model', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('value', 'decimal', [
                'default' => '0.0000',
                'null' => false,
                'precision' => 8,
                'scale' => 4,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addIndex(
                $this->index([
                        'user_id',
                        'foreign_key',
                        'model',
                    ])
                    ->setName('UNIQUE_RATING')
                    ->setType('unique')
            )
            ->addIndex(
                $this->index('user_id')
                    ->setName('user_id')
            )
            ->addIndex(
                $this->index('foreign_key')
                    ->setName('foreign_key')
            )
            ->create();

        $this->table('sandbox_users')
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('username', 'string', [
                'default' => null,
                'limit' => 30,
                'null' => false,
            ])
            ->addColumn('slug', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('password', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('email', 'string', [
                'default' => null,
                'limit' => 80,
                'null' => false,
            ])
            ->addColumn('role_id', 'tinyinteger', [
                'default' => '0',
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('status', 'tinyinteger', [
                'default' => '0',
                'limit' => null,
                'null' => false,
            ])
            ->create();

        $this->table('state_machine_item_state_logs')
            ->addColumn('state_machine_item_state_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('identifier', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addIndex(
                $this->index([
                        'identifier',
                        'state_machine_item_state_id',
                    ])
                    ->setName('identifier')
            )
            ->addIndex(
                $this->index('state_machine_item_state_id')
                    ->setName('state_machine_item_state_id')
            )
            ->create();

        $this->table('state_machine_item_states')
            ->addColumn('state_machine_process_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('name', 'string', [
                'default' => null,
                'limit' => 100,
                'null' => false,
            ])
            ->addColumn('description', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addIndex(
                $this->index([
                        'name',
                        'state_machine_process_id',
                    ])
                    ->setName('name')
                    ->setType('unique')
            )
            ->addIndex(
                $this->index('state_machine_process_id')
                    ->setName('state_machine_process_id')
            )
            ->create();

        $this->table('state_machine_items')
            ->addColumn('identifier', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('state_machine', 'string', [
                'default' => null,
                'limit' => 90,
                'null' => false,
            ])
            ->addColumn('process', 'string', [
                'default' => null,
                'limit' => 90,
                'null' => true,
            ])
            ->addColumn('state', 'string', [
                'default' => null,
                'limit' => 90,
                'null' => true,
            ])
            ->addColumn('state_machine_transition_log_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'signed' => false,
            ])
            ->addIndex(
                $this->index([
                        'identifier',
                        'state_machine',
                    ])
                    ->setName('identifier')
                    ->setType('unique')
            )
            ->create();

        $this->table('state_machine_locks')
            ->addColumn('identifier', 'string', [
                'default' => null,
                'limit' => 150,
                'null' => false,
            ])
            ->addColumn('expires', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addIndex(
                $this->index('identifier')
                    ->setName('identifier')
                    ->setType('unique')
            )
            ->create();

        $this->table('state_machine_processes')
            ->addColumn('name', 'string', [
                'default' => null,
                'limit' => 90,
                'null' => false,
            ])
            ->addColumn('state_machine', 'string', [
                'default' => null,
                'limit' => 90,
                'null' => false,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addIndex(
                $this->index([
                        'name',
                        'state_machine',
                    ])
                    ->setName('name')
                    ->setType('unique')
            )
            ->addIndex(
                $this->index('state_machine')
                    ->setName('state_machine')
            )
            ->create();

        $this->table('state_machine_timeouts')
            ->addColumn('state_machine_item_state_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('state_machine_process_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('identifier', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('event', 'string', [
                'default' => null,
                'limit' => 50,
                'null' => false,
            ])
            ->addColumn('timeout', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addIndex(
                $this->index([
                        'identifier',
                        'state_machine_item_state_id',
                    ])
                    ->setName('identifier')
                    ->setType('unique')
            )
            ->addIndex(
                $this->index('timeout')
                    ->setName('timeout')
            )
            ->addIndex(
                $this->index('state_machine_process_id')
                    ->setName('state_machine_process_id')
            )
            ->addIndex(
                $this->index('state_machine_item_state_id')
                    ->setName('state_machine_item_state_id')
            )
            ->create();

        $this->table('state_machine_transition_logs')
            ->addColumn('state_machine_process_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('state_machine_item_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('identifier', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('locked', 'boolean', [
                'default' => false,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('event', 'string', [
                'default' => null,
                'limit' => 50,
                'null' => true,
            ])
            ->addColumn('params', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('source_state', 'string', [
                'default' => null,
                'limit' => 90,
                'null' => true,
            ])
            ->addColumn('target_state', 'string', [
                'default' => null,
                'limit' => 90,
                'null' => true,
            ])
            ->addColumn('command', 'string', [
                'default' => null,
                'limit' => 90,
                'null' => true,
            ])
            ->addColumn('condition', 'string', [
                'default' => null,
                'limit' => 90,
                'null' => true,
            ])
            ->addColumn('is_error', 'boolean', [
                'default' => false,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('error_message', 'text', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addIndex(
                $this->index('state_machine_process_id')
                    ->setName('state_machine_process_id')
            )
            ->addIndex(
                $this->index('state_machine_item_id')
                    ->setName('state_machine_item_id')
            )
            ->create();

        $this->table('states')
            ->addColumn('country_id', 'integer', [
                'default' => '0',
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('code', 'string', [
                'default' => null,
                'limit' => 3,
                'null' => false,
            ])
            ->addColumn('name', 'string', [
                'default' => null,
                'limit' => 40,
                'null' => false,
            ])
            ->addColumn('lat', 'float', [
                'default' => '0',
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('lng', 'float', [
                'default' => '0',
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->create();

        $this->table('tags_tagged')
            ->addColumn('tag_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('fk_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('fk_model', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addIndex(
                $this->index([
                        'tag_id',
                        'fk_id',
                        'fk_model',
                    ])
                    ->setName('tag_id')
                    ->setType('unique')
            )
            ->create();

        $this->table('tags_tags')
            ->addColumn('namespace', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('slug', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('label', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('counter', 'integer', [
                'default' => '0',
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('color', 'string', [
                'default' => null,
                'limit' => 7,
                'null' => true,
            ])
            ->addIndex(
                $this->index([
                        'slug',
                        'namespace',
                    ])
                    ->setName('slug')
                    ->setType('unique')
            )
            ->create();

        $this->table('timezones')
            ->addColumn('name', 'string', [
                'default' => null,
                'limit' => 100,
                'null' => false,
            ])
            ->addColumn('offset', 'string', [
                'default' => null,
                'limit' => 10,
                'null' => false,
            ])
            ->addColumn('offset_dst', 'string', [
                'default' => null,
                'limit' => 10,
                'null' => false,
            ])
            ->addColumn('type', 'string', [
                'default' => null,
                'limit' => 100,
                'null' => false,
            ])
            ->addColumn('country_code', 'string', [
                'comment' => 'ISO_3166-2',
                'default' => null,
                'limit' => 2,
                'null' => true,
            ])
            ->addColumn('active', 'boolean', [
                'default' => false,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('lat', 'float', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('lng', 'float', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('covered', 'string', [
                'default' => null,
                'limit' => 190,
                'null' => true,
            ])
            ->addColumn('notes', 'string', [
                'default' => null,
                'limit' => 190,
                'null' => true,
            ])
            ->addColumn('linked_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->create();

        $this->table('translate_api_translations')
            ->addColumn('key', 'text', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('value', 'text', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('from', 'string', [
                'default' => null,
                'limit' => 6,
                'null' => false,
            ])
            ->addColumn('to', 'string', [
                'default' => null,
                'limit' => 6,
                'null' => false,
            ])
            ->addColumn('engine', 'string', [
                'default' => null,
                'limit' => 60,
                'null' => false,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->create();

        $this->table('translate_domains')
            ->addColumn('name', 'string', [
                'default' => null,
                'limit' => 60,
                'null' => false,
            ])
            ->addColumn('translate_project_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('active', 'boolean', [
                'default' => false,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('prio', 'integer', [
                'default' => '0',
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('path', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addIndex(
                $this->index([
                        'translate_project_id',
                        'name',
                    ])
                    ->setName('translate_project_id')
                    ->setType('unique')
            )
            ->addIndex(
                $this->index('translate_project_id')
                    ->setName('translate_project_id_2')
            )
            ->create();

        $this->table('translate_languages')
            ->addColumn('translate_project_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('language_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'signed' => false,
            ])
            ->addColumn('name', 'string', [
                'default' => null,
                'limit' => 12,
                'null' => false,
            ])
            ->addColumn('locale', 'string', [
                'default' => null,
                'limit' => 10,
                'null' => false,
            ])
            ->addColumn('iso2', 'string', [
                'default' => null,
                'limit' => 2,
                'null' => true,
            ])
            ->addColumn('active', 'boolean', [
                'default' => true,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('base', 'boolean', [
                'default' => false,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('primary', 'boolean', [
                'default' => false,
                'limit' => null,
                'null' => false,
            ])
            ->addIndex(
                $this->index([
                        'translate_project_id',
                        'locale',
                    ])
                    ->setName('translate_project_id')
                    ->setType('unique')
            )
            ->addIndex(
                $this->index('translate_project_id')
                    ->setName('translate_project_id_2')
            )
            ->addIndex(
                $this->index('iso2')
                    ->setName('iso2')
            )
            ->create();

        $this->table('translate_projects')
            ->addColumn('name', 'string', [
                'default' => null,
                'limit' => 60,
                'null' => false,
            ])
            ->addColumn('type', 'integer', [
                'default' => '0',
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('default', 'boolean', [
                'default' => false,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('status', 'integer', [
                'default' => '0',
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addIndex(
                $this->index('name')
                    ->setName('name')
                    ->setType('unique')
            )
            ->create();

        $this->table('translate_strings')
            ->addColumn('context', 'string', [
                'collation' => 'utf8mb4_bin',
                'default' => null,
                'limit' => 250,
                'null' => true,
            ])
            ->addColumn('name', 'text', [
                'collation' => 'utf8mb4_bin',
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('plural', 'text', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('comment', 'text', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('flags', 'string', [
                'default' => null,
                'limit' => 250,
                'null' => true,
            ])
            ->addColumn('references', 'text', [
                'comment' => 'with file and code line',
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('translate_domain_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('user_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'signed' => false,
            ])
            ->addColumn('active', 'boolean', [
                'default' => false,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('is_html', 'boolean', [
                'default' => false,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('last_import', 'date', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('skipped', 'boolean', [
                'default' => false,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('unused', 'boolean', [
                'default' => false,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('manual', 'boolean', [
                'default' => false,
                'limit' => null,
                'null' => false,
            ])
            ->addIndex(
                $this->index('translate_domain_id')
                    ->setName('translate_domain_id')
            )
            ->create();

        $this->table('translate_terms')
            ->addColumn('translate_string_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('content', 'text', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('plural_2', 'string', [
                'default' => null,
                'limit' => 250,
                'null' => true,
            ])
            ->addColumn('comment', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('translate_language_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('user_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'signed' => false,
            ])
            ->addColumn('confirmed', 'boolean', [
                'default' => false,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('confirmed_by', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'signed' => false,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addIndex(
                $this->index([
                        'translate_string_id',
                        'translate_language_id',
                    ])
                    ->setName('translate_string_id')
                    ->setType('unique')
            )
            ->addIndex(
                $this->index('translate_language_id')
                    ->setName('translate_language_id')
            )
            ->create();

        $this->table('users')
            ->addColumn('active', 'boolean', [
                'default' => false,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('last_login', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('logins', 'integer', [
                'default' => '0',
                'limit' => null,
                'null' => false,
                'signed' => false,
            ])
            ->addColumn('username', 'string', [
                'default' => null,
                'limit' => 30,
                'null' => false,
            ])
            ->addColumn('password', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('email', 'string', [
                'default' => null,
                'limit' => 80,
                'null' => false,
            ])
            ->addColumn('role_id', 'tinyinteger', [
                'default' => '0',
                'limit' => null,
                'null' => false,
            ])
            ->create();

        $this->table('workflow_contents')
            ->addColumn('user_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'signed' => false,
            ])
            ->addColumn('reviewer_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'signed' => false,
            ])
            ->addColumn('title', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('body', 'text', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('status', 'string', [
                'default' => 'draft',
                'limit' => 50,
                'null' => true,
            ])
            ->addColumn('rejection_reason', 'text', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('published_at', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addIndex(
                $this->index('user_id')
                    ->setName('user_id')
            )
            ->addIndex(
                $this->index('reviewer_id')
                    ->setName('reviewer_id')
            )
            ->addIndex(
                $this->index('status')
                    ->setName('status')
            )
            ->create();

        $this->table('workflow_documents')
            ->addColumn('user_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'signed' => false,
            ])
            ->addColumn('title', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('file_path', 'string', [
                'default' => null,
                'limit' => 500,
                'null' => true,
            ])
            ->addColumn('status', 'string', [
                'default' => 'draft',
                'limit' => 50,
                'null' => true,
            ])
            ->addColumn('current_approver_level', 'integer', [
                'default' => '0',
                'limit' => null,
                'null' => true,
                'signed' => false,
            ])
            ->addColumn('approved_by', 'text', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('rejected_by', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'signed' => false,
            ])
            ->addColumn('rejection_reason', 'text', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addIndex(
                $this->index('user_id')
                    ->setName('user_id')
            )
            ->addIndex(
                $this->index('status')
                    ->setName('status')
            )
            ->create();

        $this->table('workflow_locks')
            ->addColumn('workflow_name', 'string', [
                'default' => null,
                'limit' => 64,
                'null' => false,
            ])
            ->addColumn('entity_table', 'string', [
                'default' => null,
                'limit' => 128,
                'null' => false,
            ])
            ->addColumn('entity_id', 'string', [
                'default' => null,
                'limit' => 36,
                'null' => false,
            ])
            ->addColumn('locked_by', 'string', [
                'default' => null,
                'limit' => 128,
                'null' => true,
            ])
            ->addColumn('expires_at', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addIndex(
                $this->index([
                        'workflow_name',
                        'entity_table',
                        'entity_id',
                    ])
                    ->setName('workflow_locks_unique')
                    ->setType('unique')
            )
            ->create();

        $this->table('workflow_orders')
            ->addColumn('user_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'signed' => false,
            ])
            ->addColumn('order_number', 'string', [
                'default' => null,
                'limit' => 50,
                'null' => false,
            ])
            ->addColumn('status', 'string', [
                'default' => 'pending',
                'limit' => 50,
                'null' => true,
            ])
            ->addColumn('total', 'decimal', [
                'default' => '0.00',
                'null' => true,
                'precision' => 10,
                'scale' => 2,
            ])
            ->addColumn('payment_method', 'string', [
                'default' => null,
                'limit' => 50,
                'null' => true,
            ])
            ->addColumn('shipping_address', 'text', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('paid_at', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('shipped_at', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('delivered_at', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('cancelled_at', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addIndex(
                $this->index('order_number')
                    ->setName('order_number')
                    ->setType('unique')
            )
            ->addIndex(
                $this->index('user_id')
                    ->setName('user_id')
            )
            ->addIndex(
                $this->index('status')
                    ->setName('status')
            )
            ->create();

        $this->table('workflow_registrations')
            ->addColumn('user_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'signed' => false,
            ])
            ->addColumn('session_id', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => true,
            ])
            ->addColumn('status', 'string', [
                'default' => 'pending',
                'limit' => 50,
                'null' => true,
            ])
            ->addColumn('notes', 'text', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addIndex(
                $this->index('user_id')
                    ->setName('user_id')
            )
            ->addIndex(
                $this->index('status')
                    ->setName('status')
            )
            ->create();

        $this->table('workflow_tickets')
            ->addColumn('user_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'signed' => false,
            ])
            ->addColumn('assignee_id', 'integer', [
                'default' => null,
                'limit' => null,
                'null' => true,
                'signed' => false,
            ])
            ->addColumn('ticket_number', 'string', [
                'default' => null,
                'limit' => 50,
                'null' => false,
            ])
            ->addColumn('subject', 'string', [
                'default' => null,
                'limit' => 255,
                'null' => false,
            ])
            ->addColumn('description', 'text', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('priority', 'string', [
                'default' => 'medium',
                'limit' => 20,
                'null' => true,
            ])
            ->addColumn('status', 'string', [
                'default' => 'open',
                'limit' => 50,
                'null' => true,
            ])
            ->addColumn('escalated_at', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('resolved_at', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addIndex(
                $this->index('ticket_number')
                    ->setName('ticket_number')
                    ->setType('unique')
            )
            ->addIndex(
                $this->index('user_id')
                    ->setName('user_id')
            )
            ->addIndex(
                $this->index('assignee_id')
                    ->setName('assignee_id')
            )
            ->addIndex(
                $this->index('status')
                    ->setName('status')
            )
            ->addIndex(
                $this->index('priority')
                    ->setName('priority')
            )
            ->create();

        $this->table('workflow_timeouts')
            ->addColumn('workflow_name', 'string', [
                'default' => null,
                'limit' => 64,
                'null' => false,
            ])
            ->addColumn('entity_table', 'string', [
                'default' => null,
                'limit' => 128,
                'null' => false,
            ])
            ->addColumn('entity_id', 'string', [
                'default' => null,
                'limit' => 36,
                'null' => false,
            ])
            ->addColumn('current_state', 'string', [
                'default' => null,
                'limit' => 64,
                'null' => false,
            ])
            ->addColumn('transition_name', 'string', [
                'default' => null,
                'limit' => 64,
                'null' => false,
            ])
            ->addColumn('due_at', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('processed', 'boolean', [
                'default' => false,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addIndex(
                $this->index([
                        'due_at',
                        'processed',
                    ])
                    ->setName('due_at')
            )
            ->addIndex(
                $this->index([
                        'workflow_name',
                        'entity_table',
                        'entity_id',
                    ])
                    ->setName('workflow_name')
            )
            ->create();

        $this->table('workflow_transitions')
            ->addColumn('workflow_name', 'string', [
                'default' => null,
                'limit' => 64,
                'null' => false,
            ])
            ->addColumn('entity_table', 'string', [
                'default' => null,
                'limit' => 128,
                'null' => false,
            ])
            ->addColumn('entity_id', 'string', [
                'default' => null,
                'limit' => 36,
                'null' => false,
            ])
            ->addColumn('transition_name', 'string', [
                'default' => null,
                'limit' => 64,
                'null' => false,
            ])
            ->addColumn('from_state', 'string', [
                'default' => null,
                'limit' => 64,
                'null' => false,
            ])
            ->addColumn('to_state', 'string', [
                'default' => null,
                'limit' => 64,
                'null' => false,
            ])
            ->addColumn('user_id', 'string', [
                'default' => null,
                'limit' => 36,
                'null' => true,
            ])
            ->addColumn('reason', 'text', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('context', 'text', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('workflow_version', 'string', [
                'default' => null,
                'limit' => 16,
                'null' => true,
            ])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addIndex(
                $this->index([
                        'workflow_name',
                        'entity_table',
                        'entity_id',
                    ])
                    ->setName('workflow_name')
            )
            ->addIndex(
                $this->index([
                        'workflow_name',
                        'from_state',
                    ])
                    ->setName('workflow_name_2')
            )
            ->addIndex(
                $this->index('created')
                    ->setName('created')
            )
            ->create();

        $this->table('state_machine_item_state_logs')
            ->addForeignKey(
                $this->foreignKey('state_machine_item_state_id')
                    ->setReferencedTable('state_machine_item_states')
                    ->setReferencedColumns('id')
                    ->setDelete('CASCADE')
                    ->setUpdate('NO_ACTION')
                    ->setName('state_machine_item_state_logs_ibfk_1')
            )
            ->update();

        $this->table('state_machine_item_states')
            ->addForeignKey(
                $this->foreignKey('state_machine_process_id')
                    ->setReferencedTable('state_machine_processes')
                    ->setReferencedColumns('id')
                    ->setDelete('CASCADE')
                    ->setUpdate('NO_ACTION')
                    ->setName('state_machine_item_states_ibfk_1')
            )
            ->update();

        $this->table('state_machine_timeouts')
            ->addForeignKey(
                $this->foreignKey('state_machine_item_state_id')
                    ->setReferencedTable('state_machine_item_states')
                    ->setReferencedColumns('id')
                    ->setDelete('CASCADE')
                    ->setUpdate('NO_ACTION')
                    ->setName('state_machine_timeouts_ibfk_2')
            )
            ->addForeignKey(
                $this->foreignKey('state_machine_process_id')
                    ->setReferencedTable('state_machine_processes')
                    ->setReferencedColumns('id')
                    ->setDelete('CASCADE')
                    ->setUpdate('NO_ACTION')
                    ->setName('state_machine_timeouts_ibfk_1')
            )
            ->update();

        $this->table('state_machine_transition_logs')
            ->addForeignKey(
                $this->foreignKey('state_machine_item_id')
                    ->setReferencedTable('state_machine_items')
                    ->setReferencedColumns('id')
                    ->setDelete('CASCADE')
                    ->setUpdate('NO_ACTION')
                    ->setName('state_machine_transition_logs_ibfk_2')
            )
            ->addForeignKey(
                $this->foreignKey('state_machine_process_id')
                    ->setReferencedTable('state_machine_processes')
                    ->setReferencedColumns('id')
                    ->setDelete('CASCADE')
                    ->setUpdate('NO_ACTION')
                    ->setName('state_machine_transition_logs_ibfk_1')
            )
            ->update();

        $this->table('translate_domains')
            ->addForeignKey(
                $this->foreignKey('translate_project_id')
                    ->setReferencedTable('translate_projects')
                    ->setReferencedColumns('id')
                    ->setDelete('CASCADE')
                    ->setUpdate('NO_ACTION')
                    ->setName('translate_domains_ibfk_1')
            )
            ->update();

        $this->table('translate_languages')
            ->addForeignKey(
                $this->foreignKey('translate_project_id')
                    ->setReferencedTable('translate_projects')
                    ->setReferencedColumns('id')
                    ->setDelete('CASCADE')
                    ->setUpdate('NO_ACTION')
                    ->setName('translate_languages_ibfk_1')
            )
            ->update();

        $this->table('translate_strings')
            ->addForeignKey(
                $this->foreignKey('translate_domain_id')
                    ->setReferencedTable('translate_domains')
                    ->setReferencedColumns('id')
                    ->setDelete('CASCADE')
                    ->setUpdate('NO_ACTION')
                    ->setName('translate_strings_ibfk_1')
            )
            ->update();

        $this->table('translate_terms')
            ->addForeignKey(
                $this->foreignKey('translate_language_id')
                    ->setReferencedTable('translate_languages')
                    ->setReferencedColumns('id')
                    ->setDelete('CASCADE')
                    ->setUpdate('NO_ACTION')
                    ->setName('translate_terms_ibfk_2')
            )
            ->addForeignKey(
                $this->foreignKey('translate_string_id')
                    ->setReferencedTable('translate_strings')
                    ->setReferencedColumns('id')
                    ->setDelete('CASCADE')
                    ->setUpdate('NO_ACTION')
                    ->setName('translate_terms_ibfk_1')
            )
            ->update();
    }

    /**
     * Down Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/5/en/migrations.html#the-down-method
     *
     * @return void
     */
    public function down(): void
    {
        $this->table('state_machine_item_state_logs')
            ->dropForeignKey(
                'state_machine_item_state_id'
            )->save();

        $this->table('state_machine_item_states')
            ->dropForeignKey(
                'state_machine_process_id'
            )->save();

        $this->table('state_machine_timeouts')
            ->dropForeignKey(
                'state_machine_item_state_id'
            )
            ->dropForeignKey(
                'state_machine_process_id'
            )->save();

        $this->table('state_machine_transition_logs')
            ->dropForeignKey(
                'state_machine_item_id'
            )
            ->dropForeignKey(
                'state_machine_process_id'
            )->save();

        $this->table('translate_domains')
            ->dropForeignKey(
                'translate_project_id'
            )->save();

        $this->table('translate_languages')
            ->dropForeignKey(
                'translate_project_id'
            )->save();

        $this->table('translate_strings')
            ->dropForeignKey(
                'translate_domain_id'
            )->save();

        $this->table('translate_terms')
            ->dropForeignKey(
                'translate_language_id'
            )
            ->dropForeignKey(
                'translate_string_id'
            )->save();

        $this->table('audit_logs')->drop()->save();
        $this->table('bitmasked_records')->drop()->save();
        $this->table('bouncer_records')->drop()->save();
        $this->table('cake_seeds')->drop()->save();
        $this->table('captchas')->drop()->save();
        $this->table('comments_comments')->drop()->save();
        $this->table('continents')->drop()->save();
        $this->table('countries')->drop()->save();
        $this->table('currencies')->drop()->save();
        $this->table('database_logs')->drop()->save();
        $this->table('demo_articles')->drop()->save();
        $this->table('demo_articles_translations')->drop()->save();
        $this->table('events')->drop()->save();
        $this->table('exposed_users')->drop()->save();
        $this->table('favorites_favorites')->drop()->save();
        $this->table('file_storage')->drop()->save();
        $this->table('languages')->drop()->save();
        $this->table('queue_processes')->drop()->save();
        $this->table('queue_scheduler_rows')->drop()->save();
        $this->table('queued_jobs')->drop()->save();
        $this->table('registrations')->drop()->save();
        $this->table('roles')->drop()->save();
        $this->table('sandbox_animals')->drop()->save();
        $this->table('sandbox_articles')->drop()->save();
        $this->table('sandbox_categories')->drop()->save();
        $this->table('sandbox_cities')->drop()->save();
        $this->table('sandbox_posts')->drop()->save();
        $this->table('sandbox_products')->drop()->save();
        $this->table('sandbox_profiles')->drop()->save();
        $this->table('sandbox_ratings')->drop()->save();
        $this->table('sandbox_users')->drop()->save();
        $this->table('state_machine_item_state_logs')->drop()->save();
        $this->table('state_machine_item_states')->drop()->save();
        $this->table('state_machine_items')->drop()->save();
        $this->table('state_machine_locks')->drop()->save();
        $this->table('state_machine_processes')->drop()->save();
        $this->table('state_machine_timeouts')->drop()->save();
        $this->table('state_machine_transition_logs')->drop()->save();
        $this->table('states')->drop()->save();
        $this->table('tags_tagged')->drop()->save();
        $this->table('tags_tags')->drop()->save();
        $this->table('timezones')->drop()->save();
        $this->table('translate_api_translations')->drop()->save();
        $this->table('translate_domains')->drop()->save();
        $this->table('translate_languages')->drop()->save();
        $this->table('translate_projects')->drop()->save();
        $this->table('translate_strings')->drop()->save();
        $this->table('translate_terms')->drop()->save();
        $this->table('users')->drop()->save();
        $this->table('workflow_contents')->drop()->save();
        $this->table('workflow_documents')->drop()->save();
        $this->table('workflow_locks')->drop()->save();
        $this->table('workflow_orders')->drop()->save();
        $this->table('workflow_registrations')->drop()->save();
        $this->table('workflow_tickets')->drop()->save();
        $this->table('workflow_timeouts')->drop()->save();
        $this->table('workflow_transitions')->drop()->save();
    }
}
