@setup
    require __DIR__.'/vendor/autoload.php';

    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__, '.env');
    try {
        $dotenv->load();
        $dotenv->required([
            'DEPLOY_USER',
            'DEPLOY_SERVER',
            'DEPLOY_BASE_DIR',
            'DEPLOY_REPO',
        ])->notEmpty();
    } catch ( Exception $e )  {
        echo $e->getMessage();
    }
    
    $user = env('DEPLOY_USER');
    $repo = env('DEPLOY_REPO');
    
    if (!isset($baseDir)) {
        $baseDir = env('DEPLOY_BASE_DIR');
    }

    if (!isset($branch)) {
        $branch = 'master';
    }

    if (!isset($appEnv)) {
        $appEnv = env('DEPLOY_APP_ENV');
    }

    $releaseDir = $baseDir . '/releases';
    $currentDir = $baseDir . '/current';
    $release = date('YmdHis');
    $currentReleaseDir = $releaseDir . '/' . $release;

    function logMessage($message) {
        return "echo '\033[32m" .$message. "\033[0m';\n";
    }
@endsetup

@servers(['prod' => [env('DEPLOY_USER').'@'.env('DEPLOY_SERVER')]])

@task('rollback', ['on' => 'prod', 'confirm' => true])
    {{ logMessage("ロールバック実行") }}
    cd {{ $releaseDir }}
    ln -nfs {{ $releaseDir }}/$(find . -maxdepth 1 -name "20*" | sort  | tail -n 2 | head -n1) {{ $baseDir }}/current
    {{ logMessage("ロールバック完了") }}

    {{ logMessage("キャッシュ再構築") }}
    php {{ $currentDir }}/artisan optimize -q

    {{ logMessage("キャッシュの再構築完了") }}

    echo "ロールバック: $(find . -maxdepth 1 -name "20*" | sort  | tail -n 2 | head -n1)"
@endtask

@task('init', ['on' => 'prod', 'confirm' => true])

if [ ! -d {{ $baseDir }}/current ]; then
    cd {{ $baseDir }}

    git clone {{ $repo }} --branch={{ $branch }} --depth=1 -q {{ $release }}
    {{ logMessage("ブランチクローン") }}

    mv {{ $release }}/storage {{ $baseDir }}/storage
    ln -nfs {{ $baseDir }}/storage {{ $release }}/storage
    ln -nfs {{ $baseDir }}/storage/public {{ $release }}/public/storage
    {{ logMessage("ストレージ初期化") }}

    cp {{ $release }}/.env.{{ $appEnv }} {{ $baseDir }}/.env
    ln -nfs {{ $baseDir }}/.env {{ $release }}/.env
    {{ logMessage("環境ファイルセットアップ") }}

    sudo chown -R {{ $user }}:www-data {{ $baseDir }}/storage
    sudo chmod -R ug+rwx {{ $baseDir }}/storage

    rm -rf {{ $release }}
    {{ logMessage("デプロイメントパスが初期化されました。") }}
else
    {{ logMessage("デプロイメントパスは既に初期化されています。") }}
fi
@endtask

@story('deploy', ['on' => 'prod'])
    git
    composer
    yarn_install
    yarn_run_prod
    update_symlinks
    set_permissions
    reload_services
    cache
    clean_old_releases
@endstory

@task('git')
    {{ logMessage("ブランチクローン") }}

    git clone {{ $repo }} --branch={{ $branch }} --depth=1 -q {{ $currentReleaseDir }}
@endtask

@task('composer')
    {{ logMessage("Composer セットアップ") }}

    cd {{ $currentReleaseDir }}

    composer install --no-interaction --quiet --no-dev --prefer-dist --optimize-autoloader
@endtask

@task('yarn_install')
    {{ logMessage("Node modules 初期化") }}

    cd {{ $currentReleaseDir }}

    yarn --silent --no-progress > /dev/null
@endtask

@task('yarn_run_prod')
    {{ logMessage("Yarn ビルド") }}

    cd {{ $currentReleaseDir }}

    yarn prod --silent --no-progress > /dev/null

    {{ logMessage("node_modules削除") }}
    rm -rf node_modules
@endtask

@task('update_symlinks')
    {{ logMessage("シンボリックリンク更新") }}

    {{ logMessage("Storageシンボリックリンク設定") }}
    rm -rf {{ $currentReleaseDir }}/storage;
    cd {{ $currentReleaseDir }};
    ln -nfs {{ $baseDir }}/storage {{ $currentReleaseDir }}/storage;
    ln -nfs {{ $baseDir }}/storage/app/public {{ $currentReleaseDir }}/public/storage

    {{ logMessage("環境ファイル設定") }}
    cd {{ $currentReleaseDir }};
    ln -nfs {{ $baseDir }}/.env .env;

    {{ logMessage("環境ファイル設定完了") }}
    ln -nfs {{ $currentReleaseDir }} {{ $currentDir }};
@endtask

@task('set_permissions')
    # Set dir permissions
    {{ logMessage("パーミッション設定") }}

    sudo chown -R {{ $user }}:www-data {{ $baseDir }}
    sudo chmod -R ug+rwx {{ $baseDir }}/storage
    cd {{ $baseDir }}
    sudo chown -R {{ $user }}:www-data current
    sudo chmod -R ug+rwx current/storage current/bootstrap/cache
    sudo chown -R {{ $user }}:www-data {{ $currentReleaseDir }}
@endtask

@task('cache')
    {{ logMessage("ビルドキャッシュ") }}

    php {{ $currentDir }}/artisan optimize -q
@endtask

@task('clean_old_releases')
    {{ logMessage("古いリリース削除") }}
    cd {{ $releaseDir }}
    ls -dt {{ $releaseDir }}/* | tail -n +2 | xargs rm -rf;
@endtask


@task('reload_services', ['on' => 'prod'])
    # Reload Services
@endtask


@finished
    echo "デプロイ終了\r\n";
@endfinished