<?php

/**
 * Git workspace service for content sync, remote setup, and merge sessions.
 */

declare(strict_types=1);

/**
 * Encapsulates Git CLI workflows for the admin workspace.
 */
final class GitWorkspace
{
    /**
     * Stores the base path.
     *
     * @var string
     */
    private $basePath;

    /**
     * Stores config.
     *
     * @var array<string, mixed>
     */
    private $config;

    /**
     * Stores repository root.
     *
     * @var string
     */
    private $repositoryRoot;

    /**
     * Stores the merge session root path.
     *
     * @var string
     */
    private $mergeSessionRoot;

    /**
     * Stores managed repo paths.
     *
     * @var string[]
     */
    private $managedRepoPaths = array();

    /**
     * Initializes the Git workspace configuration and managed content roots.
     *
     * @param array<string, mixed> $config
     * @param string[] $managedProjectPaths
     */
    public function __construct(string $basePath, array $config = array(), array $managedProjectPaths = array())
    {
        $this->basePath = rtrim(str_replace('\\', '/', $basePath), '/');
        $this->config = $this->normalizeConfig($config);
        $this->repositoryRoot = $this->resolvePath((string) ($this->config['repositoryRoot'] ?? '.'));
        $this->mergeSessionRoot = $this->resolvePath((string) ($this->config['mergeSessionRoot'] ?? 'cache/admin-git-merge'));
        $this->managedRepoPaths = $this->normalizeManagedRepoPaths($managedProjectPaths);
    }

    /**
     * Determines whether Git features are enabled for the admin workspace.
     */
    public function isEnabled(): bool
    {
        return !empty($this->config['enabled']);
    }

    /**
     * Builds the safe Git configuration payload for the admin bootstrap.
     *
     * @return array<string, mixed>
     */
    public function getClientConfig(): array
    {
        return array(
            'enabled' => $this->isEnabled(),
            'remoteName' => (string) ($this->config['remoteName'] ?? 'origin'),
            'defaultBranch' => (string) ($this->config['defaultBranch'] ?? 'main'),
            'allowRemoteSetup' => !empty($this->config['allowRemoteSetup']),
            'allowPull' => !array_key_exists('allowPull', $this->config) || !empty($this->config['allowPull']),
            'allowPush' => !array_key_exists('allowPush', $this->config) || !empty($this->config['allowPush']),
            'repositoryRoot' => $this->projectPathFromAbsolute($this->repositoryRoot) ?: '.',
        );
    }

    /**
     * Returns the current Git workspace status.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $status = array(
            'enabled' => $this->isEnabled(),
            'available' => false,
            'isRepository' => false,
            'repositoryRoot' => $this->projectPathFromAbsolute($this->repositoryRoot) ?: '.',
            'remoteName' => (string) ($this->config['remoteName'] ?? 'origin'),
            'remoteUrl' => '',
            'branch' => '',
            'upstream' => '',
            'ahead' => 0,
            'behind' => 0,
            'dirty' => false,
            'mergeInProgress' => false,
            'files' => array(),
            'message' => '',
            'allowRemoteSetup' => !empty($this->config['allowRemoteSetup']),
            'allowPull' => !array_key_exists('allowPull', $this->config) || !empty($this->config['allowPull']),
            'allowPush' => !array_key_exists('allowPush', $this->config) || !empty($this->config['allowPush']),
            'mergeSession' => null,
        );

        if (!$this->isEnabled()) {
            $status['message'] = 'Git-Integration ist deaktiviert.';
            return $status;
        }

        if (!$this->isGitAvailable()) {
            $status['message'] = 'Git ist auf diesem Server nicht verfuegbar.';
            return $status;
        }

        $status['available'] = true;

        $repositoryCheck = $this->runGit(array('rev-parse', '--show-toplevel'));
        if (($repositoryCheck['exitCode'] ?? 1) !== 0) {
            $status['message'] = 'Das konfigurierte Repository konnte nicht erkannt werden.';
            return $status;
        }

        $topLevel = trim((string) ($repositoryCheck['stdout'] ?? ''));
        if ($topLevel === '') {
            $status['message'] = 'Das konfigurierte Repository konnte nicht erkannt werden.';
            return $status;
        }

        $status['isRepository'] = true;
        $status['repositoryRoot'] = $this->projectPathFromAbsolute(str_replace('\\', '/', $topLevel)) ?: '.';
        $status['branch'] = $this->readCurrentBranch();
        $status['upstream'] = $this->readUpstreamRef();
        $status['remoteUrl'] = $this->readRemoteUrl((string) ($status['remoteName'] ?? 'origin'));
        $status['mergeInProgress'] = $this->isMergeInProgress();
        $status['files'] = $this->readWorkingTreeFiles();
        $status['dirty'] = $status['files'] !== array();
        $status['mergeSession'] = $this->buildMergeSessionSummary($this->loadMergeSession());

        $branchLine = $this->readStatusBranchLine();
        if ($branchLine !== '') {
            $aheadBehind = $this->parseAheadBehind($branchLine);
            $status['ahead'] = $aheadBehind['ahead'];
            $status['behind'] = $aheadBehind['behind'];
        }

        if ($status['mergeInProgress'] && $status['mergeSession'] === null) {
            $status['message'] = 'Es laeuft bereits ein Git-Merge ausserhalb der Admin-Session.';
        } elseif ($status['remoteUrl'] === '') {
            $status['message'] = 'Noch kein Git-Remote konfiguriert.';
        } elseif ($status['mergeSession'] !== null) {
            $status['message'] = 'Ein Merge wartet auf manuelle Aufloesung.';
        } elseif ($status['dirty']) {
            $status['message'] = 'Es gibt lokale Aenderungen im Repository.';
        } else {
            $status['message'] = 'Repository ist synchronisiert oder bereit fuer den naechsten Sync.';
        }

        return $status;
    }

    /**
     * Configures or updates the tracked remote and refreshes its refs.
     *
     * @return array<string, mixed>
     */
    public function setupRemote(string $remoteUrl, string $remoteName = '', string $branch = ''): array
    {
        if (!$this->isEnabled()) {
            return $this->errorResult('Git-Integration ist deaktiviert.');
        }

        if (empty($this->config['allowRemoteSetup'])) {
            return $this->errorResult('Remote-Setup ist in der Konfiguration deaktiviert.');
        }

        if (!$this->ensureRepository()) {
            return $this->errorResult('Kein gueltiges Git-Repository gefunden.');
        }

        $remoteName = $this->normalizeRefName($remoteName !== '' ? $remoteName : (string) ($this->config['remoteName'] ?? 'origin'));
        $branch = $this->normalizeRefName($branch !== '' ? $branch : (string) ($this->config['defaultBranch'] ?? 'main'));
        $remoteUrl = trim($remoteUrl);

        if ($remoteName === '' || $remoteUrl === '') {
            return $this->errorResult('Remote-Name und Remote-URL sind erforderlich.');
        }

        $existingRemotes = preg_split('/\r?\n/', trim((string) ($this->runGit(array('remote'))['stdout'] ?? ''))) ?: array();
        if (in_array($remoteName, $existingRemotes, true)) {
            $setUrl = $this->runGit(array('remote', 'set-url', $remoteName, $remoteUrl));
            if (($setUrl['exitCode'] ?? 1) !== 0) {
                return $this->errorResult('Remote-URL konnte nicht aktualisiert werden.', $setUrl);
            }
        } else {
            $addRemote = $this->runGit(array('remote', 'add', $remoteName, $remoteUrl));
            if (($addRemote['exitCode'] ?? 1) !== 0) {
                return $this->errorResult('Remote konnte nicht angelegt werden.', $addRemote);
            }
        }

        $fetch = $this->runGit(array('fetch', $remoteName));
        if (($fetch['exitCode'] ?? 1) !== 0) {
            return $this->errorResult('Remote wurde gespeichert, konnte aber nicht abgerufen werden.', $fetch, $this->status());
        }

        $currentBranch = $this->readCurrentBranch();
        if ($currentBranch !== '' && $branch !== '') {
            $remoteBranchExists = $this->runGit(array('show-ref', '--verify', '--quiet', 'refs/remotes/' . $remoteName . '/' . $branch));
            if (($remoteBranchExists['exitCode'] ?? 1) === 0) {
                $this->runGit(array('branch', '--set-upstream-to', $remoteName . '/' . $branch, $currentBranch));
            }
        }

        return array(
            'ok' => true,
            'message' => 'Remote-Konfiguration aktualisiert.',
            'status' => $this->status(),
        );
    }

    /**
     * Fetches the configured remote refs.
     *
     * @return array<string, mixed>
     */
    public function fetch(): array
    {
        if (!$this->isEnabled()) {
            return $this->errorResult('Git-Integration ist deaktiviert.');
        }

        if (!$this->ensureRepository()) {
            return $this->errorResult('Kein gueltiges Git-Repository gefunden.');
        }

        $remoteName = (string) ($this->config['remoteName'] ?? 'origin');
        if ($this->readRemoteUrl($remoteName) === '') {
            return $this->errorResult('Noch kein Remote konfiguriert.', null, $this->status());
        }

        $result = $this->runGit(array('fetch', $remoteName));
        if (($result['exitCode'] ?? 1) !== 0) {
            return $this->errorResult('Fetch ist fehlgeschlagen.', $result, $this->status());
        }

        return array(
            'ok' => true,
            'message' => 'Remote-Refs aktualisiert.',
            'status' => $this->status(),
        );
    }

    /**
     * Stages managed content changes and creates a new local commit.
     *
     * @return array<string, mixed>
     */
    public function commit(string $message): array
    {
        if (!$this->isEnabled()) {
            return $this->errorResult('Git-Integration ist deaktiviert.');
        }

        if (!$this->ensureRepository()) {
            return $this->errorResult('Kein gueltiges Git-Repository gefunden.');
        }

        if ($this->isMergeInProgress() || $this->loadMergeSession() !== null) {
            return $this->errorResult('Ein aktiver Merge muss zuerst abgeschlossen oder abgebrochen werden.', null, $this->status());
        }

        $message = trim($message);
        if ($message === '') {
            $message = 'Update content';
        }

        $stage = $this->stageManagedChanges();
        if (($stage['exitCode'] ?? 1) !== 0) {
            return $this->errorResult('Aenderungen konnten nicht fuer den Commit vorbereitet werden.', $stage, $this->status());
        }

        if (!$this->hasStagedChanges()) {
            return $this->errorResult('Es gibt keine commitbaren Content-Aenderungen.', null, $this->status());
        }

        $commit = $this->runGitCommit(array('commit', '-m', $message));
        if (($commit['exitCode'] ?? 1) !== 0) {
            return $this->errorResult('Commit ist fehlgeschlagen.', $commit, $this->status());
        }

        return array(
            'ok' => true,
            'message' => 'Commit erstellt.',
            'status' => $this->status(),
        );
    }

    /**
     * Pulls remote changes with fast-forward or merge-session fallbacks.
     *
     * @param callable|null $beforeWrite
     * @return array<string, mixed>
     */
    public function pull(?callable $beforeWrite = null): array
    {
        if (!$this->isEnabled()) {
            return $this->errorResult('Git-Integration ist deaktiviert.');
        }

        if (empty($this->config['allowPull'])) {
            return $this->errorResult('Pull ist in der Konfiguration deaktiviert.');
        }

        if (!$this->ensureRepository()) {
            return $this->errorResult('Kein gueltiges Git-Repository gefunden.');
        }

        $activeSession = $this->loadMergeSession();
        if ($activeSession !== null) {
            return array(
                'ok' => false,
                'message' => 'Ein Merge laeuft bereits und wartet auf Aufloesung.',
                'status' => $this->status(),
                'mergeSession' => $this->sanitizeMergeSession($activeSession),
            );
        }

        if ($this->isMergeInProgress()) {
            return $this->errorResult('Im Repository laeuft bereits ein Merge ausserhalb der Admin-Session.', null, $this->status());
        }

        $status = $this->status();
        if (!empty($status['dirty'])) {
            return $this->errorResult('Bitte zuerst lokale Aenderungen committen oder rueckgaengig machen.', null, $status);
        }

        $fetch = $this->fetch();
        if (empty($fetch['ok'])) {
            return $fetch;
        }

        $status = $this->status();
        $upstream = $status['upstream'] !== ''
            ? (string) $status['upstream']
            : ((string) ($status['remoteName'] ?? 'origin')) . '/' . ((string) ($this->config['defaultBranch'] ?? 'main'));

        if (($status['behind'] ?? 0) <= 0) {
            return array(
                'ok' => true,
                'message' => 'Keine neuen Remote-Aenderungen fuer Pull gefunden.',
                'status' => $status,
            );
        }

        if (($status['ahead'] ?? 0) === 0) {
            $pathsToSnapshot = $this->diffProjectPaths('HEAD', $upstream);
            if ($beforeWrite !== null && $pathsToSnapshot !== array()) {
                $beforeWrite($pathsToSnapshot);
            }

            $merge = $this->runGit(array('merge', '--ff-only', $upstream));
            if (($merge['exitCode'] ?? 1) !== 0) {
                return $this->errorResult('Fast-Forward-Pull ist fehlgeschlagen.', $merge, $this->status());
            }

            return array(
                'ok' => true,
                'message' => 'Remote-Aenderungen wurden per Fast-Forward uebernommen.',
                'status' => $this->status(),
                'shouldReload' => true,
            );
        }

        $analysis = $this->analyzeDivergence($upstream);
        if ($analysis['blockedPaths'] !== array()) {
            return array(
                'ok' => false,
                'message' => 'Der Pull erzeugt Konflikte ausserhalb der im Browser unterstuetzten Content-Dateien.',
                'blockedPaths' => $analysis['blockedPaths'],
                'status' => $status,
            );
        }

        if ($beforeWrite !== null && $analysis['snapshotPaths'] !== array()) {
            $beforeWrite($analysis['snapshotPaths']);
        }

        $merge = $this->runGit(array('merge', '--no-commit', '--no-ff', $upstream));
        $conflictedPaths = $this->readConflictedRepoPaths();

        if (($merge['exitCode'] ?? 1) > 1) {
            $this->runGit(array('merge', '--abort'));
            return $this->errorResult('Pull-Merge ist fehlgeschlagen.', $merge, $this->status());
        }

        if ($conflictedPaths === array()) {
            $commit = $this->runGitCommit(array('commit', '--no-edit'));
            if (($commit['exitCode'] ?? 1) !== 0) {
                return $this->errorResult('Der automatische Merge konnte nicht abgeschlossen werden.', $commit, $this->status());
            }

            return array(
                'ok' => true,
                'message' => 'Remote-Aenderungen wurden zusammengefuehrt.',
                'status' => $this->status(),
                'shouldReload' => true,
            );
        }

        foreach ($conflictedPaths as $repoPath) {
            if (!$this->isMergeableRepoPath($repoPath)) {
                $this->runGit(array('merge', '--abort'));
                return array(
                    'ok' => false,
                    'message' => 'Der Pull enthaelt Konflikte ausserhalb der Browser-Merge-Pfade.',
                    'blockedPaths' => array_values(array_unique(array_map(array($this, 'displayPathFromRepoPath'), $conflictedPaths))),
                    'status' => $this->status(),
                );
            }
        }

        $session = $this->createMergeSession($analysis, $conflictedPaths, $upstream);

        return array(
            'ok' => true,
            'message' => 'Pull-Konflikte wurden fuer die Browser-Merge aufbereitet.',
            'status' => $this->status(),
            'mergeSession' => $this->sanitizeMergeSession($session),
        );
    }

    /**
     * Pushes the current branch to the configured remote.
     *
     * @return array<string, mixed>
     */
    public function push(): array
    {
        if (!$this->isEnabled()) {
            return $this->errorResult('Git-Integration ist deaktiviert.');
        }

        if (empty($this->config['allowPush'])) {
            return $this->errorResult('Push ist in der Konfiguration deaktiviert.');
        }

        if (!$this->ensureRepository()) {
            return $this->errorResult('Kein gueltiges Git-Repository gefunden.');
        }

        if ($this->isMergeInProgress() || $this->loadMergeSession() !== null) {
            return $this->errorResult('Ein aktiver Merge muss zuerst abgeschlossen oder abgebrochen werden.', null, $this->status());
        }

        $status = $this->status();
        $fetch = $this->fetch();
        if (empty($fetch['ok'])) {
            return $fetch;
        }

        $status = $this->status();
        if (($status['behind'] ?? 0) > 0) {
            return array(
                'ok' => false,
                'message' => 'Der Remote-Branch ist weiter. Bitte zuerst pullen.',
                'requiresPull' => true,
                'status' => $status,
            );
        }

        $remoteName = (string) ($status['remoteName'] ?? 'origin');
        $branch = (string) ($status['branch'] ?? '');
        if ($branch === '') {
            return $this->errorResult('Der aktuelle Branch konnte nicht ermittelt werden.', null, $status);
        }

        $command = $status['upstream'] === ''
            ? array('push', '-u', $remoteName, $branch)
            : array('push', $remoteName, $branch);
        $push = $this->runGit($command);
        if (($push['exitCode'] ?? 1) !== 0) {
            return $this->errorResult('Push ist fehlgeschlagen.', $push, $this->status());
        }

        return array(
            'ok' => true,
            'message' => 'Branch wurde zum Remote gepusht.',
            'status' => $this->status(),
        );
    }

    /**
     * Builds a review payload with unified diffs for managed workspace changes.
     *
     * @param string[] $projectPaths
     * @return array<string, mixed>
     */
    public function review(array $projectPaths = array()): array
    {
        if (!$this->isEnabled()) {
            return $this->errorResult('Git-Integration ist deaktiviert.');
        }

        if (!$this->ensureRepository()) {
            return $this->errorResult('Kein gueltiges Git-Repository gefunden.');
        }

        $status = $this->status();
        $filters = array();
        foreach ($projectPaths as $projectPath) {
            if (!is_string($projectPath)) {
                continue;
            }

            $normalized = $this->normalizePath($projectPath);
            if ($normalized !== '') {
                $filters[$normalized] = $normalized;
            }
        }

        $files = array();
        $unmanagedFiles = array();
        foreach ((array) ($status['files'] ?? array()) as $file) {
            if (!is_array($file)) {
                continue;
            }

            $path = $this->normalizePath((string) ($file['path'] ?? ''));
            $oldPath = $this->normalizePath((string) ($file['oldPath'] ?? ''));
            if ($filters !== array() && !isset($filters[$path]) && ($oldPath === '' || !isset($filters[$oldPath]))) {
                continue;
            }

            if (empty($file['isManaged'])) {
                if ($path !== '') {
                    $unmanagedFiles[] = $path;
                }
                continue;
            }

            $files[] = $file + $this->buildReviewDiffPayload($file);
        }

        return array(
            'ok' => true,
            'status' => $status,
            'files' => $files,
            'unmanagedFiles' => array_values(array_unique($unmanagedFiles)),
        );
    }

    /**
     * Lists local and remote branches for the configured repository.
     *
     * @return array<string, mixed>
     */
    public function branches(): array
    {
        if (!$this->isEnabled()) {
            return $this->errorResult('Git-Integration ist deaktiviert.');
        }

        if (!$this->ensureRepository()) {
            return $this->errorResult('Kein gueltiges Git-Repository gefunden.');
        }

        $localResult = $this->runGit(array(
            'branch',
            '--format=%(refname:short)%x1f%(upstream:short)%x1f%(objectname:short)%x1f%(HEAD)',
        ));
        $remoteResult = $this->runGit(array(
            'for-each-ref',
            '--format=%(refname:short)%x1f%(objectname:short)',
            'refs/remotes',
        ));

        return array(
            'ok' => true,
            'branches' => array(
                'current' => $this->readCurrentBranch(),
                'local' => $this->parseLocalBranchLines((string) ($localResult['stdout'] ?? '')),
                'remote' => $this->parseRemoteBranchLines((string) ($remoteResult['stdout'] ?? '')),
            ),
            'status' => $this->status(),
        );
    }

    /**
     * Checks out an existing branch or creates a prefixed editorial branch.
     *
     * @return array<string, mixed>
     */
    public function checkoutBranch(string $branch, bool $create = false, string $from = ''): array
    {
        if (!$this->isEnabled()) {
            return $this->errorResult('Git-Integration ist deaktiviert.');
        }

        if (!$this->ensureRepository()) {
            return $this->errorResult('Kein gueltiges Git-Repository gefunden.');
        }

        if ($this->isMergeInProgress() || $this->loadMergeSession() !== null) {
            return $this->errorResult('Ein aktiver Merge muss zuerst abgeschlossen oder abgebrochen werden.', null, $this->status());
        }

        $status = $this->status();
        if (!empty($status['dirty'])) {
            return $this->errorResult('Bitte zuerst lokale Aenderungen committen oder verwerfen, bevor der Branch gewechselt wird.', null, $status);
        }

        $branch = $this->normalizeRefName($branch);
        if ($branch === '') {
            return $this->errorResult('Ein Branch-Name ist erforderlich.', null, $status);
        }

        $remoteName = (string) ($this->config['remoteName'] ?? 'origin');
        if ($create) {
            $branch = $this->normalizeBranchNameForCreate($branch);
            $from = $this->normalizeRefName($from !== '' ? $from : ($status['branch'] !== '' ? (string) $status['branch'] : (string) ($this->config['defaultBranch'] ?? 'main')));

            if ($this->localBranchExists($branch) || $this->remoteBranchExists($remoteName . '/' . $branch)) {
                return $this->errorResult('Der gewuenschte Branch existiert bereits.', null, $status);
            }

            $command = array('checkout', '-b', $branch);
            if ($from !== '') {
                $command[] = $from;
            }
            $checkout = $this->runGit($command);
        } else {
            if ($this->localBranchExists($branch)) {
                $checkout = $this->runGit(array('checkout', $branch));
            } elseif ($this->remoteBranchExists($remoteName . '/' . $branch)) {
                $checkout = $this->runGit(array('checkout', '--track', $remoteName . '/' . $branch));
            } else {
                return $this->errorResult('Der Branch konnte lokal oder auf dem Remote nicht gefunden werden.', null, $status);
            }
        }

        if (($checkout['exitCode'] ?? 1) !== 0) {
            return $this->errorResult('Der Branch-Wechsel ist fehlgeschlagen.', $checkout, $this->status());
        }

        return array(
            'ok' => true,
            'message' => $create ? 'Branch wurde angelegt und ausgecheckt.' : 'Branch wurde gewechselt.',
            'status' => $this->status(),
            'branches' => $this->branches()['branches'],
            'shouldReload' => true,
        );
    }

    /**
     * Returns recent managed commits for editorial review and rollback tasks.
     *
     * @return array<string, mixed>
     */
    public function history(int $limit = 12): array
    {
        if (!$this->isEnabled()) {
            return $this->errorResult('Git-Integration ist deaktiviert.');
        }

        if (!$this->ensureRepository()) {
            return $this->errorResult('Kein gueltiges Git-Repository gefunden.');
        }

        $limit = max(1, min(30, $limit));
        $result = $this->runGit(array_merge(
            array(
                'log',
                '--date=iso-strict',
                '--pretty=format:__COMMIT__%n%H%x1f%h%x1f%an%x1f%ad%x1f%s',
                '--name-status',
                '-n',
                (string) $limit,
                '--',
            ),
            $this->managedPathArguments()
        ));
        if (($result['exitCode'] ?? 1) !== 0) {
            $details = trim((string) (($result['stderr'] ?? '') !== '' ? $result['stderr'] : ($result['stdout'] ?? '')));
            if (stripos($details, 'does not have any commits yet') !== false) {
                return array(
                    'ok' => true,
                    'history' => array(),
                    'status' => $this->status(),
                );
            }

            return $this->errorResult('Die Git-Historie konnte nicht geladen werden.', $result, $this->status());
        }

        return array(
            'ok' => true,
            'history' => $this->parseHistoryLog((string) ($result['stdout'] ?? '')),
            'status' => $this->status(),
        );
    }

    /**
     * Restores a managed file from a specific revision into the working tree.
     *
     * @param callable|null $beforeWrite
     * @return array<string, mixed>
     */
    public function restoreFile(string $projectPath, string $revision, ?callable $beforeWrite = null): array
    {
        if (!$this->isEnabled()) {
            return $this->errorResult('Git-Integration ist deaktiviert.');
        }

        if (!$this->ensureRepository()) {
            return $this->errorResult('Kein gueltiges Git-Repository gefunden.');
        }

        if ($this->isMergeInProgress() || $this->loadMergeSession() !== null) {
            return $this->errorResult('Ein aktiver Merge muss zuerst abgeschlossen oder abgebrochen werden.', null, $this->status());
        }

        $projectPath = $this->normalizePath($projectPath);
        $revision = trim($revision);
        if ($projectPath === '' || $revision === '') {
            return $this->errorResult('Dateipfad und Revision sind erforderlich.', null, $this->status());
        }

        $absolutePath = $this->resolvePath($projectPath);
        $repoPath = $this->repoPathFromAbsolute($absolutePath);
        if ($repoPath === '' || !$this->isManagedRepoPath($repoPath)) {
            return $this->errorResult('Nur verwaltete Content-Dateien koennen aus Git wiederhergestellt werden.', null, $this->status());
        }

        if ($beforeWrite !== null) {
            $beforeWrite(array($projectPath));
        }

        $exists = $this->runGit(array('cat-file', '-e', $revision . ':' . $repoPath));
        if (($exists['exitCode'] ?? 1) === 0) {
            $content = $this->runGit(array('show', $revision . ':' . $repoPath));
            if (($content['exitCode'] ?? 1) !== 0) {
                return $this->errorResult('Die Dateiversion konnte nicht aus Git gelesen werden.', $content, $this->status());
            }

            $directory = str_replace('\\', '/', dirname($absolutePath));
            if (!is_dir($directory)) {
                $this->ensureDirectory($directory);
            }

            file_put_contents($absolutePath, (string) ($content['stdout'] ?? ''));
        } else {
            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }
        }

        return array(
            'ok' => true,
            'message' => 'Datei wurde aus der ausgewaehlten Revision wiederhergestellt.',
            'status' => $this->status(),
            'restoredPath' => $projectPath,
            'shouldReload' => true,
        );
    }

    /**
     * Collects Git and remote connectivity diagnostics for the admin UI.
     *
     * @return array<string, mixed>
     */
    public function diagnostics(): array
    {
        $status = $this->status();
        $gitVersion = trim((string) ($this->runCommand(array('git', '--version'), $this->repositoryRoot)['stdout'] ?? ''));
        $remoteName = (string) ($status['remoteName'] ?? $this->config['remoteName'] ?? 'origin');
        $remoteUrl = (string) ($status['remoteUrl'] ?? '');
        $probe = null;

        if ($remoteUrl !== '') {
            $probeResult = $this->runGitNonInteractive(array('ls-remote', '--heads', $remoteName));
            $probeOutput = trim((string) (($probeResult['stderr'] ?? '') !== '' ? $probeResult['stderr'] : ($probeResult['stdout'] ?? '')));
            $probe = array(
                'ok' => ($probeResult['exitCode'] ?? 1) === 0,
                'message' => $probeOutput !== '' ? $probeOutput : (($probeResult['exitCode'] ?? 1) === 0 ? 'Remote ist erreichbar.' : 'Remote-Probe fehlgeschlagen.'),
                'exitCode' => (int) ($probeResult['exitCode'] ?? 1),
            );
        }

        return array(
            'ok' => true,
            'diagnostics' => array(
                'gitVersion' => $gitVersion,
                'repositoryRoot' => (string) ($status['repositoryRoot'] ?? '.'),
                'isRepository' => !empty($status['isRepository']),
                'branch' => (string) ($status['branch'] ?? ''),
                'upstream' => (string) ($status['upstream'] ?? ''),
                'remoteName' => $remoteName,
                'remoteUrl' => $remoteUrl,
                'remoteProbe' => $probe,
                'authorName' => (string) ($this->config['authorName'] ?? ''),
                'authorEmail' => (string) ($this->config['authorEmail'] ?? ''),
                'credentialHelpers' => $this->readCredentialHelpers(),
                'environment' => array(
                    'sshAuthSock' => trim((string) getenv('SSH_AUTH_SOCK')) !== '',
                    'gitAskPass' => trim((string) getenv('GIT_ASKPASS')) !== '',
                    'sshCommand' => trim((string) getenv('GIT_SSH_COMMAND')) !== '',
                    'home' => trim((string) getenv('HOME')) !== '' || trim((string) getenv('USERPROFILE')) !== '',
                ),
            ),
            'status' => $status,
        );
    }

    /**
     * Returns the active merge session payload for the admin UI.
     *
     * @return array<string, mixed>|null
     */
    public function getMergeSession(string $sessionId = ''): ?array
    {
        $session = $this->loadMergeSession();
        if ($session === null) {
            return null;
        }

        if ($sessionId !== '' && !hash_equals((string) ($session['id'] ?? ''), $sessionId)) {
            return null;
        }

        return $this->sanitizeMergeSession($session);
    }

    /**
     * Applies resolved merge results to the working tree and finalizes the merge commit.
     *
     * @param array<int, array<string, mixed>> $files
     * @param callable|null $beforeWrite
     * @return array<string, mixed>
     */
    public function applyMergeSession(string $sessionId, array $files, ?callable $beforeWrite = null): array
    {
        $session = $this->loadMergeSession();
        if ($session === null || !hash_equals((string) ($session['id'] ?? ''), $sessionId)) {
            return $this->errorResult('Keine passende Merge-Session gefunden.', null, $this->status());
        }

        if (!$this->isMergeInProgress()) {
            $this->clearMergeSession();
            return $this->errorResult('Der zugrunde liegende Git-Merge ist nicht mehr aktiv.', null, $this->status());
        }

        $payloadByPath = array();
        foreach ($files as $file) {
            if (!is_array($file)) {
                continue;
            }

            $path = $this->normalizePath((string) ($file['path'] ?? ''));
            if ($path !== '') {
                $payloadByPath[$path] = $file;
            }
        }

        $pathsToSnapshot = array();
        foreach ((array) ($session['files'] ?? array()) as $sessionFile) {
            if (!is_array($sessionFile)) {
                continue;
            }

            $projectPath = $this->normalizePath((string) ($sessionFile['path'] ?? ''));
            if ($projectPath !== '') {
                $pathsToSnapshot[] = $projectPath;
            }
        }

        if ($beforeWrite !== null && $pathsToSnapshot !== array()) {
            $beforeWrite(array_values(array_unique($pathsToSnapshot)));
        }

        foreach ((array) ($session['files'] ?? array()) as $sessionFile) {
            if (!is_array($sessionFile)) {
                continue;
            }

            $projectPath = $this->normalizePath((string) ($sessionFile['path'] ?? ''));
            $repoPath = $this->normalizePath((string) ($sessionFile['repoPath'] ?? ''));
            if ($projectPath === '' || $repoPath === '') {
                continue;
            }

            $resolution = $payloadByPath[$projectPath] ?? array();
            $selection = strtolower(trim((string) ($resolution['selection'] ?? 'manual')));
            $content = (string) ($resolution['result'] ?? (string) ($sessionFile['result'] ?? ''));
            $delete = false;

            if ($selection === 'local') {
                $content = (string) ($sessionFile['local'] ?? '');
                $delete = empty($sessionFile['existsLocal']);
            } elseif ($selection === 'remote') {
                $content = (string) ($sessionFile['remote'] ?? '');
                $delete = empty($sessionFile['existsRemote']);
            }

            $absolutePath = $this->resolvePath($projectPath);
            $directory = str_replace('\\', '/', dirname($absolutePath));
            if (!is_dir($directory)) {
                $this->ensureDirectory($directory);
            }

            if ($delete) {
                if (is_file($absolutePath)) {
                    @unlink($absolutePath);
                }
                continue;
            }

            file_put_contents($absolutePath, $content);
        }

        $stagePaths = array();
        foreach ((array) ($session['files'] ?? array()) as $sessionFile) {
            if (!is_array($sessionFile)) {
                continue;
            }

            $repoPath = $this->normalizePath((string) ($sessionFile['repoPath'] ?? ''));
            if ($repoPath !== '') {
                $stagePaths[$repoPath] = $repoPath;
            }
        }

        $stage = $this->runGit(array_merge(array('add', '-A', '--'), array_values($stagePaths)));
        if (($stage['exitCode'] ?? 1) !== 0) {
            return $this->errorResult('Die Merge-Ergebnisse konnten nicht vorgemerkt werden.', $stage, $this->status());
        }

        if ($this->readConflictedRepoPaths() !== array()) {
            return $this->errorResult('Es bleiben noch ungeloeste Konflikte im Merge bestehen.', null, $this->status());
        }

        $commit = $this->runGitCommit(array('commit', '--no-edit'));
        if (($commit['exitCode'] ?? 1) !== 0) {
            return $this->errorResult('Der Merge-Commit konnte nicht abgeschlossen werden.', $commit, $this->status());
        }

        $this->clearMergeSession();

        return array(
            'ok' => true,
            'message' => 'Merge wurde uebernommen und committet.',
            'status' => $this->status(),
            'shouldReload' => true,
        );
    }

    /**
     * Aborts the active merge session and resets the repository to the pre-merge state.
     *
     * @return array<string, mixed>
     */
    public function cancelMergeSession(string $sessionId = ''): array
    {
        $session = $this->loadMergeSession();
        if ($sessionId !== '' && $session !== null && !hash_equals((string) ($session['id'] ?? ''), $sessionId)) {
            return $this->errorResult('Keine passende Merge-Session gefunden.', null, $this->status());
        }

        if ($this->isMergeInProgress()) {
            $abort = $this->runGit(array('merge', '--abort'));
            if (($abort['exitCode'] ?? 1) !== 0) {
                return $this->errorResult('Der aktive Merge konnte nicht abgebrochen werden.', $abort, $this->status());
            }
        }

        $this->clearMergeSession();

        return array(
            'ok' => true,
            'message' => 'Merge-Session wurde abgebrochen.',
            'status' => $this->status(),
        );
    }

    /**
     * Normalizes Git configuration values.
     *
     * @return array<string, mixed>
     */
    private function normalizeConfig(array $config): array
    {
        return array(
            'enabled' => !empty($config['enabled']),
            'repositoryRoot' => $this->normalizePath((string) ($config['repositoryRoot'] ?? '.')),
            'remoteName' => $this->normalizeRefName((string) ($config['remoteName'] ?? 'origin')) ?: 'origin',
            'defaultBranch' => $this->normalizeRefName((string) ($config['defaultBranch'] ?? 'main')) ?: 'main',
            'allowRemoteSetup' => !array_key_exists('allowRemoteSetup', $config) || !empty($config['allowRemoteSetup']),
            'allowPull' => !array_key_exists('allowPull', $config) || !empty($config['allowPull']),
            'allowPush' => !array_key_exists('allowPush', $config) || !empty($config['allowPush']),
            'authorName' => trim((string) ($config['authorName'] ?? 'WorldMesh CMS')),
            'authorEmail' => trim((string) ($config['authorEmail'] ?? 'cms@example.invalid')),
            'mergeSessionRoot' => $this->normalizePath((string) ($config['mergeSessionRoot'] ?? 'cache/admin-git-merge')),
        );
    }

    /**
     * Normalizes managed content roots into repo-relative Git paths.
     *
     * @param string[] $managedProjectPaths
     * @return string[]
     */
    private function normalizeManagedRepoPaths(array $managedProjectPaths): array
    {
        $paths = array();

        foreach ($managedProjectPaths as $projectPath) {
            if (!is_string($projectPath)) {
                continue;
            }

            $projectPath = $this->normalizePath($projectPath);
            if ($projectPath === '') {
                continue;
            }

            $absolutePath = $this->resolvePath($projectPath);
            $repoPath = $this->repoPathFromAbsolute($absolutePath);
            if ($repoPath === '') {
                continue;
            }

            $paths[$repoPath] = $repoPath;
        }

        return array_values($paths);
    }

    /**
     * Determines whether Git CLI is available.
     */
    private function isGitAvailable(): bool
    {
        $result = $this->runCommand(array('git', '--version'), $this->repositoryRoot);
        return ($result['exitCode'] ?? 1) === 0;
    }

    /**
     * Determines whether the configured repository root is a valid Git work tree.
     */
    private function ensureRepository(): bool
    {
        $result = $this->runGit(array('rev-parse', '--is-inside-work-tree'));
        return trim((string) ($result['stdout'] ?? '')) === 'true';
    }

    /**
     * Reads the current branch name.
     */
    private function readCurrentBranch(): string
    {
        $result = $this->runGit(array('branch', '--show-current'));
        return trim((string) ($result['stdout'] ?? ''));
    }

    /**
     * Reads the current upstream ref.
     */
    private function readUpstreamRef(): string
    {
        $result = $this->runGit(array('rev-parse', '--abbrev-ref', '--symbolic-full-name', '@{upstream}'));
        return ($result['exitCode'] ?? 1) === 0 ? trim((string) ($result['stdout'] ?? '')) : '';
    }

    /**
     * Reads the configured remote URL.
     */
    private function readRemoteUrl(string $remoteName): string
    {
        $result = $this->runGit(array('remote', 'get-url', $remoteName));
        return ($result['exitCode'] ?? 1) === 0 ? trim((string) ($result['stdout'] ?? '')) : '';
    }

    /**
     * Reads the branch header from `git status`.
     */
    private function readStatusBranchLine(): string
    {
        $result = $this->runGit(array('status', '--porcelain=1', '--branch'));
        if (($result['exitCode'] ?? 1) !== 0) {
            return '';
        }

        $lines = preg_split('/\r?\n/', (string) ($result['stdout'] ?? '')) ?: array();
        return isset($lines[0]) ? trim((string) $lines[0]) : '';
    }

    /**
     * Parses ahead/behind counters from a porcelain branch line.
     *
     * @return array<string, int>
     */
    private function parseAheadBehind(string $branchLine): array
    {
        $summary = array('ahead' => 0, 'behind' => 0);

        if (preg_match('/\[([^\]]+)\]/', $branchLine, $matches) !== 1) {
            return $summary;
        }

        $parts = preg_split('/,\s*/', (string) ($matches[1] ?? '')) ?: array();
        foreach ($parts as $part) {
            if (preg_match('/ahead\s+(\d+)/i', $part, $aheadMatch) === 1) {
                $summary['ahead'] = (int) ($aheadMatch[1] ?? 0);
            }
            if (preg_match('/behind\s+(\d+)/i', $part, $behindMatch) === 1) {
                $summary['behind'] = (int) ($behindMatch[1] ?? 0);
            }
        }

        return $summary;
    }

    /**
     * Reads working tree file status entries.
     *
     * @return array<int, array<string, mixed>>
     */
    private function readWorkingTreeFiles(): array
    {
        $result = $this->runGit(array('status', '--porcelain=1', '--branch'));
        if (($result['exitCode'] ?? 1) !== 0) {
            return array();
        }

        $lines = preg_split('/\r?\n/', trim((string) ($result['stdout'] ?? ''))) ?: array();
        $files = array();

        foreach ($lines as $line) {
            if ($line === '' || strpos($line, '## ') === 0) {
                continue;
            }

            $status = substr($line, 0, 2);
            $pathPart = trim(substr($line, 3));
            $oldRepoPath = '';
            $repoPath = $pathPart;

            if (strpos($pathPart, ' -> ') !== false) {
                $parts = preg_split('/\s+->\s+/', $pathPart, 2) ?: array('', '');
                $oldRepoPath = $this->normalizePath((string) ($parts[0] ?? ''));
                $repoPath = (string) ($parts[1] ?? '');
            }

            $repoPath = $this->normalizePath($repoPath);
            if ($repoPath === '') {
                continue;
            }

            $projectPath = $this->projectPathFromRepoPath($repoPath);
            $oldProjectPath = $oldRepoPath !== '' ? $this->projectPathFromRepoPath($oldRepoPath) : '';

            $files[] = array(
                'path' => $projectPath !== '' ? $projectPath : $repoPath,
                'projectPath' => $projectPath,
                'repoPath' => $repoPath,
                'oldPath' => $oldProjectPath !== '' ? $oldProjectPath : $oldRepoPath,
                'indexStatus' => substr($status, 0, 1),
                'workTreeStatus' => substr($status, 1, 1),
                'status' => $status,
                'isUntracked' => $status === '??',
                'isConflict' => $this->isConflictStatus($status),
                'isDeleted' => strpos($status, 'D') !== false,
                'isRenamed' => strpos($status, 'R') !== false,
                'isManaged' => $this->isManagedRepoPath($repoPath),
                'isMergeable' => $this->isMergeableRepoPath($repoPath),
            );
        }

        usort($files, static function (array $left, array $right): int {
            return strnatcasecmp((string) ($left['path'] ?? ''), (string) ($right['path'] ?? ''));
        });

        return $files;
    }

    /**
     * Determines whether a porcelain status token describes a merge conflict.
     */
    private function isConflictStatus(string $status): bool
    {
        return in_array($status, array('DD', 'AU', 'UD', 'UA', 'DU', 'AA', 'UU'), true);
    }

    /**
     * Stages all managed content paths.
     *
     * @return array<string, mixed>
     */
    private function stageManagedChanges(): array
    {
        if ($this->managedRepoPaths === array()) {
            return array(
                'exitCode' => 0,
                'stdout' => '',
                'stderr' => '',
            );
        }

        return $this->runGit(array_merge(array('add', '-A', '--'), $this->managedRepoPaths));
    }

    /**
     * Determines whether there are staged changes waiting to be committed.
     */
    private function hasStagedChanges(): bool
    {
        $result = $this->runGit(array('diff', '--cached', '--quiet', '--'));
        return ($result['exitCode'] ?? 1) === 1;
    }

    /**
     * Determines whether a Git merge is currently in progress.
     */
    private function isMergeInProgress(): bool
    {
        $result = $this->runGit(array('rev-parse', '-q', '--verify', 'MERGE_HEAD'));
        return ($result['exitCode'] ?? 1) === 0;
    }

    /**
     * Returns repo-relative conflicted paths from the current merge state.
     *
     * @return string[]
     */
    private function readConflictedRepoPaths(): array
    {
        $result = $this->runGit(array('diff', '--name-only', '--diff-filter=U'));
        if (($result['exitCode'] ?? 1) !== 0) {
            return array();
        }

        $paths = preg_split('/\r?\n/', trim((string) ($result['stdout'] ?? ''))) ?: array();
        $normalized = array();
        foreach ($paths as $path) {
            $path = $this->normalizePath($path);
            if ($path !== '') {
                $normalized[$path] = $path;
            }
        }

        return array_values($normalized);
    }

    /**
     * Analyzes a divergent local/remote history before starting the merge.
     *
     * @return array<string, mixed>
     */
    private function analyzeDivergence(string $upstream): array
    {
        $mergeBaseResult = $this->runGit(array('merge-base', 'HEAD', $upstream));
        $mergeBase = trim((string) ($mergeBaseResult['stdout'] ?? ''));

        $localChanged = $mergeBase !== '' ? $this->diffRepoPaths($mergeBase, 'HEAD') : array();
        $remoteChanged = $mergeBase !== '' ? $this->diffRepoPaths($mergeBase, $upstream) : array();
        $allPaths = array_values(array_unique(array_merge($localChanged, $remoteChanged)));
        $changedOnBothSides = array_values(array_intersect($localChanged, $remoteChanged));
        $blockedPaths = array();
        $mergeEntries = array();
        $snapshotPaths = array();

        foreach ($allPaths as $repoPath) {
            $projectPath = $this->projectPathFromRepoPath($repoPath);
            if ($projectPath !== '' && $this->isEditableProjectPath($projectPath)) {
                $snapshotPaths[$projectPath] = $projectPath;
            }
        }

        foreach ($changedOnBothSides as $repoPath) {
            if (!$this->revisionsDiffer('HEAD', $upstream, $repoPath)) {
                continue;
            }

            if (!$this->isMergeableRepoPath($repoPath)) {
                $blockedPaths[] = $this->displayPathFromRepoPath($repoPath);
                continue;
            }

            $mergeEntries[$repoPath] = $this->buildMergeEntry($mergeBase, 'HEAD', $upstream, $repoPath);
        }

        sort($blockedPaths, SORT_NATURAL | SORT_FLAG_CASE);

        return array(
            'mergeBase' => $mergeBase,
            'localChanged' => $localChanged,
            'remoteChanged' => $remoteChanged,
            'mergeEntries' => $mergeEntries,
            'blockedPaths' => $blockedPaths,
            'snapshotPaths' => array_values($snapshotPaths),
        );
    }

    /**
     * Returns repo-relative paths changed between two revisions.
     *
     * @return string[]
     */
    private function diffRepoPaths(string $fromRevision, string $toRevision): array
    {
        $result = $this->runGit(array('diff', '--name-only', '--diff-filter=ACDMRT', $fromRevision, $toRevision, '--'));
        if (($result['exitCode'] ?? 1) !== 0) {
            return array();
        }

        $paths = preg_split('/\r?\n/', trim((string) ($result['stdout'] ?? ''))) ?: array();
        $normalized = array();
        foreach ($paths as $path) {
            $path = $this->normalizePath($path);
            if ($path !== '') {
                $normalized[$path] = $path;
            }
        }

        return array_values($normalized);
    }

    /**
     * Returns project-relative paths changed between two revisions.
     *
     * @return string[]
     */
    private function diffProjectPaths(string $fromRevision, string $toRevision): array
    {
        $paths = array();
        foreach ($this->diffRepoPaths($fromRevision, $toRevision) as $repoPath) {
            $projectPath = $this->projectPathFromRepoPath($repoPath);
            if ($projectPath !== '' && $this->isEditableProjectPath($projectPath)) {
                $paths[$projectPath] = $projectPath;
            }
        }

        return array_values($paths);
    }

    /**
     * Determines whether the final file contents differ between two revisions.
     */
    private function revisionsDiffer(string $leftRevision, string $rightRevision, string $repoPath): bool
    {
        $result = $this->runGit(array('diff', '--quiet', $leftRevision, $rightRevision, '--', $repoPath));
        return ($result['exitCode'] ?? 1) === 1;
    }

    /**
     * Builds a merge entry with base, local, remote, and pre-merged content.
     *
     * @return array<string, mixed>
     */
    private function buildMergeEntry(string $mergeBase, string $localRevision, string $remoteRevision, string $repoPath): array
    {
        $baseInfo = $this->readRevisionContent($mergeBase, $repoPath);
        $localInfo = $this->readRevisionContent($localRevision, $repoPath);
        $remoteInfo = $this->readRevisionContent($remoteRevision, $repoPath);
        $mergePreview = $this->mergeTextVersions(
            (string) ($localInfo['content'] ?? ''),
            (string) ($baseInfo['content'] ?? ''),
            (string) ($remoteInfo['content'] ?? '')
        );

        return array(
            'path' => $this->displayPathFromRepoPath($repoPath),
            'repoPath' => $repoPath,
            'base' => (string) ($baseInfo['content'] ?? ''),
            'local' => (string) ($localInfo['content'] ?? ''),
            'remote' => (string) ($remoteInfo['content'] ?? ''),
            'result' => (string) ($mergePreview['content'] ?? ''),
            'hasConflict' => !empty($mergePreview['hasConflict']),
            'existsBase' => !empty($baseInfo['exists']),
            'existsLocal' => !empty($localInfo['exists']),
            'existsRemote' => !empty($remoteInfo['exists']),
        );
    }

    /**
     * Reads file contents for a specific revision.
     *
     * @return array<string, mixed>
     */
    private function readRevisionContent(string $revision, string $repoPath): array
    {
        if ($revision === '') {
            return array(
                'exists' => false,
                'content' => '',
            );
        }

        $exists = $this->runGit(array('cat-file', '-e', $revision . ':' . $repoPath));
        if (($exists['exitCode'] ?? 1) !== 0) {
            return array(
                'exists' => false,
                'content' => '',
            );
        }

        $content = $this->runGit(array('show', $revision . ':' . $repoPath));
        return array(
            'exists' => ($content['exitCode'] ?? 1) === 0,
            'content' => ($content['exitCode'] ?? 1) === 0 ? (string) ($content['stdout'] ?? '') : '',
        );
    }

    /**
     * Creates a conflict session for the current merge state.
     *
     * @param string[] $conflictedRepoPaths
     * @return array<string, mixed>
     */
    private function createMergeSession(array $analysis, array $conflictedRepoPaths, string $upstream): array
    {
        $files = array();
        foreach ($conflictedRepoPaths as $repoPath) {
            $files[] = $analysis['mergeEntries'][$repoPath] ?? $this->buildMergeEntry(
                (string) ($analysis['mergeBase'] ?? ''),
                'HEAD',
                $upstream,
                $repoPath
            );
        }

        usort($files, static function (array $left, array $right): int {
            return strnatcasecmp((string) ($left['path'] ?? ''), (string) ($right['path'] ?? ''));
        });

        $session = array(
            'id' => bin2hex(random_bytes(8)),
            'createdAt' => date('c'),
            'branch' => $this->readCurrentBranch(),
            'upstream' => $upstream,
            'files' => $files,
        );

        $this->persistMergeSession($session);

        return $session;
    }

    /**
     * Loads the persisted merge session for the configured repository.
     *
     * @return array<string, mixed>|null
     */
    private function loadMergeSession(): ?array
    {
        $path = $this->mergeSessionFilePath();
        if (!is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            return null;
        }

        if (!$this->isMergeInProgress()) {
            $this->clearMergeSession();
            return null;
        }

        return $payload;
    }

    /**
     * Persists the active merge session payload.
     *
     * @param array<string, mixed> $session
     */
    private function persistMergeSession(array $session): void
    {
        $this->ensureDirectory($this->mergeSessionRoot);
        file_put_contents(
            $this->mergeSessionFilePath(),
            json_encode($session, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Deletes the persisted merge session file.
     */
    private function clearMergeSession(): void
    {
        $path = $this->mergeSessionFilePath();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * Builds the merge session file path for the configured repository.
     */
    private function mergeSessionFilePath(): string
    {
        return $this->mergeSessionRoot . '/' . sha1($this->repositoryRoot) . '.json';
    }

    /**
     * Builds the lightweight merge session summary for status payloads.
     *
     * @param array<string, mixed>|null $session
     * @return array<string, mixed>|null
     */
    private function buildMergeSessionSummary(?array $session): ?array
    {
        if ($session === null) {
            return null;
        }

        $paths = array();
        foreach ((array) ($session['files'] ?? array()) as $file) {
            if (!is_array($file)) {
                continue;
            }

            $path = $this->normalizePath((string) ($file['path'] ?? ''));
            if ($path !== '') {
                $paths[] = $path;
            }
        }

        return array(
            'id' => (string) ($session['id'] ?? ''),
            'createdAt' => (string) ($session['createdAt'] ?? ''),
            'branch' => (string) ($session['branch'] ?? ''),
            'upstream' => (string) ($session['upstream'] ?? ''),
            'fileCount' => count($paths),
            'paths' => $paths,
        );
    }

    /**
     * Sanitizes merge session payloads for client-side consumption.
     *
     * @param array<string, mixed> $session
     * @return array<string, mixed>
     */
    private function sanitizeMergeSession(array $session): array
    {
        $files = array();
        foreach ((array) ($session['files'] ?? array()) as $file) {
            if (!is_array($file)) {
                continue;
            }

            $files[] = array(
                'path' => (string) ($file['path'] ?? ''),
                'base' => (string) ($file['base'] ?? ''),
                'local' => (string) ($file['local'] ?? ''),
                'remote' => (string) ($file['remote'] ?? ''),
                'result' => (string) ($file['result'] ?? ''),
                'hasConflict' => !empty($file['hasConflict']),
                'existsBase' => !empty($file['existsBase']),
                'existsLocal' => !empty($file['existsLocal']),
                'existsRemote' => !empty($file['existsRemote']),
            );
        }

        return array(
            'id' => (string) ($session['id'] ?? ''),
            'createdAt' => (string) ($session['createdAt'] ?? ''),
            'branch' => (string) ($session['branch'] ?? ''),
            'upstream' => (string) ($session['upstream'] ?? ''),
            'files' => $files,
        );
    }

    /**
     * Merges text versions using Git's merge-file algorithm.
     *
     * @return array<string, mixed>
     */
    private function mergeTextVersions(string $local, string $base, string $remote): array
    {
        $localFile = tempnam(sys_get_temp_dir(), 'git-local-');
        $baseFile = tempnam(sys_get_temp_dir(), 'git-base-');
        $remoteFile = tempnam(sys_get_temp_dir(), 'git-remote-');
        if (!is_string($localFile) || !is_string($baseFile) || !is_string($remoteFile)) {
            return array(
                'content' => $local,
                'hasConflict' => true,
            );
        }

        file_put_contents($localFile, $local);
        file_put_contents($baseFile, $base);
        file_put_contents($remoteFile, $remote);

        $result = $this->runCommand(array('git', 'merge-file', '-p', $localFile, $baseFile, $remoteFile), $this->repositoryRoot);

        @unlink($localFile);
        @unlink($baseFile);
        @unlink($remoteFile);

        return array(
            'content' => (string) ($result['stdout'] ?? ''),
            'hasConflict' => ($result['exitCode'] ?? 1) === 1,
        );
    }

    /**
     * Builds a diff preview payload for a single managed status entry.
     *
     * @param array<string, mixed> $file
     * @return array<string, mixed>
     */
    private function buildReviewDiffPayload(array $file): array
    {
        $repoPath = $this->normalizePath((string) ($file['repoPath'] ?? ''));
        $projectPath = $this->normalizePath((string) ($file['path'] ?? ''));

        if ($repoPath === '') {
            return array(
                'patch' => 'Kein Git-Pfad fuer diese Aenderung verfuegbar.',
                'patchKind' => 'summary',
                'hasPatch' => false,
                'isBinary' => false,
            );
        }

        if (!$this->isTextDiffCandidate($repoPath)) {
            return array(
                'patch' => 'Fuer diese Datei wird im Browser keine Text-Diff-Vorschau erzeugt.',
                'patchKind' => 'summary',
                'hasPatch' => false,
                'isBinary' => true,
            );
        }

        if (!empty($file['isUntracked'])) {
            $absolutePath = $this->resolvePath($projectPath !== '' ? $projectPath : $repoPath);
            return $this->buildUntrackedDiffPayload($absolutePath, $projectPath !== '' ? $projectPath : $repoPath);
        }

        $patch = $this->buildWorkingTreeDiff($repoPath);
        if ($patch === '') {
            $patch = 'Keine textuelle Diff-Ausgabe verfuegbar.';
        }

        return array(
            'patch' => $patch,
            'patchKind' => strpos($patch, '@@') !== false || strpos($patch, 'diff --git') !== false ? 'diff' : 'summary',
            'hasPatch' => $patch !== '' && $patch !== 'Keine textuelle Diff-Ausgabe verfuegbar.',
            'isBinary' => false,
        );
    }

    /**
     * Builds a unified diff for staged and unstaged changes of a tracked path.
     */
    private function buildWorkingTreeDiff(string $repoPath): string
    {
        $repoPath = $this->normalizePath($repoPath);
        if ($repoPath === '') {
            return '';
        }

        $chunks = array();
        foreach (array(false, true) as $cached) {
            $arguments = array('diff', '--no-ext-diff', '--unified=3');
            if ($cached) {
                $arguments[] = '--cached';
            }
            $arguments[] = '--';
            $arguments[] = $repoPath;

            $result = $this->runGit($arguments);
            $patch = trim((string) ($result['stdout'] ?? ''));
            if ($patch !== '') {
                $chunks[] = $patch;
            }
        }

        return implode("\n\n", array_values(array_unique($chunks)));
    }

    /**
     * Builds a synthetic diff payload for an untracked file.
     *
     * @return array<string, mixed>
     */
    private function buildUntrackedDiffPayload(string $absolutePath, string $displayPath): array
    {
        if (!is_file($absolutePath)) {
            return array(
                'patch' => 'Ungetracktes Verzeichnis oder nicht lesbarer Pfad: ' . $displayPath,
                'patchKind' => 'summary',
                'hasPatch' => false,
                'isBinary' => false,
            );
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'git-empty-');
        if (!is_string($temporaryPath)) {
            return array(
                'patch' => 'Ungetrackte Datei: ' . $displayPath,
                'patchKind' => 'summary',
                'hasPatch' => false,
                'isBinary' => false,
            );
        }

        file_put_contents($temporaryPath, '');
        $result = $this->runCommand(
            array('git', 'diff', '--no-index', '--no-ext-diff', '--unified=3', $temporaryPath, $absolutePath),
            $this->repositoryRoot
        );
        @unlink($temporaryPath);

        $patch = trim((string) ($result['stdout'] ?? ''));
        if ($patch === '') {
            $content = @file_get_contents($absolutePath);
            $patch = is_string($content) ? $content : 'Ungetrackte Datei: ' . $displayPath;
            $kind = 'preview';
        } else {
            $kind = 'diff';
        }

        return array(
            'patch' => $patch,
            'patchKind' => $kind,
            'hasPatch' => $patch !== '',
            'isBinary' => false,
        );
    }

    /**
     * Determines whether a repo path is suitable for browser-based text diffs.
     */
    private function isTextDiffCandidate(string $repoPath): bool
    {
        $extension = strtolower(pathinfo($repoPath, PATHINFO_EXTENSION));
        return in_array($extension, array('md', 'txt', 'yaml', 'yml', 'json', 'svg', 'css', 'js', 'php', 'html', 'xml', 'csv'), true);
    }

    /**
     * Parses local branch lines from Git's custom branch format.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseLocalBranchLines(string $stdout): array
    {
        $entries = array();
        $lines = preg_split('/\r?\n/', trim($stdout)) ?: array();
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = explode("\x1f", $line);
            $entries[] = array(
                'name' => (string) ($parts[0] ?? ''),
                'upstream' => (string) ($parts[1] ?? ''),
                'commit' => (string) ($parts[2] ?? ''),
                'isCurrent' => trim((string) ($parts[3] ?? '')) === '*',
            );
        }

        return $entries;
    }

    /**
     * Parses remote branch lines from Git's custom ref format.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseRemoteBranchLines(string $stdout): array
    {
        $entries = array();
        $lines = preg_split('/\r?\n/', trim($stdout)) ?: array();
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = explode("\x1f", $line);
            $name = (string) ($parts[0] ?? '');
            if ($name === '' || substr($name, -5) === '/HEAD') {
                continue;
            }

            $segments = explode('/', $name, 2);
            $entries[] = array(
                'name' => isset($segments[1]) ? $segments[1] : $name,
                'fullName' => $name,
                'commit' => (string) ($parts[1] ?? ''),
            );
        }

        return $entries;
    }

    /**
     * Parses a `git log --name-status` payload into structured commit entries.
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseHistoryLog(string $stdout): array
    {
        $lines = preg_split('/\r?\n/', trim($stdout)) ?: array();
        $entries = array();
        $current = null;
        $expectsMeta = false;

        foreach ($lines as $line) {
            if ($line === '__COMMIT__') {
                if ($current !== null) {
                    $entries[] = $current;
                }
                $current = null;
                $expectsMeta = true;
                continue;
            }

            if ($expectsMeta) {
                $parts = explode("\x1f", $line, 5);
                $current = array(
                    'hash' => (string) ($parts[0] ?? ''),
                    'shortHash' => (string) ($parts[1] ?? ''),
                    'author' => (string) ($parts[2] ?? ''),
                    'date' => (string) ($parts[3] ?? ''),
                    'subject' => (string) ($parts[4] ?? ''),
                    'files' => array(),
                );
                $expectsMeta = false;
                continue;
            }

            if ($current === null || trim($line) === '') {
                continue;
            }

            $parts = preg_split("/\t+/", trim($line)) ?: array();
            $status = (string) ($parts[0] ?? '');
            $oldRepoPath = '';
            $repoPath = '';
            if (strpos($status, 'R') === 0 || strpos($status, 'C') === 0) {
                $oldRepoPath = $this->normalizePath((string) ($parts[1] ?? ''));
                $repoPath = $this->normalizePath((string) ($parts[2] ?? ''));
            } else {
                $repoPath = $this->normalizePath((string) ($parts[1] ?? ''));
            }

            if ($repoPath === '' || !$this->isManagedRepoPath($repoPath)) {
                continue;
            }

            $current['files'][] = array(
                'status' => $status,
                'repoPath' => $repoPath,
                'path' => $this->displayPathFromRepoPath($repoPath),
                'oldPath' => $oldRepoPath !== '' ? $this->displayPathFromRepoPath($oldRepoPath) : '',
            );
        }

        if ($current !== null) {
            $entries[] = $current;
        }

        return $entries;
    }

    /**
     * Builds the managed pathspec list used for log and diff commands.
     *
     * @return string[]
     */
    private function managedPathArguments(): array
    {
        return $this->managedRepoPaths !== array() ? $this->managedRepoPaths : array('.');
    }

    /**
     * Checks whether a local branch exists.
     */
    private function localBranchExists(string $branch): bool
    {
        $result = $this->runGit(array('show-ref', '--verify', '--quiet', 'refs/heads/' . $branch));
        return ($result['exitCode'] ?? 1) === 0;
    }

    /**
     * Checks whether a remote-tracking branch exists.
     */
    private function remoteBranchExists(string $branch): bool
    {
        $result = $this->runGit(array('show-ref', '--verify', '--quiet', 'refs/remotes/' . $branch));
        return ($result['exitCode'] ?? 1) === 0;
    }

    /**
     * Normalizes branch names for new editorial branches using the required prefix.
     */
    private function normalizeBranchNameForCreate(string $branch): string
    {
        $branch = $this->normalizeRefName($branch);
        if ($branch === '') {
            return '';
        }

        return strpos($branch, 'vulkan/') === 0 ? $branch : 'vulkan/' . ltrim($branch, '/');
    }

    /**
     * Runs Git commands without interactive credential prompts.
     *
     * @param string[] $arguments
     * @return array<string, mixed>
     */
    private function runGitNonInteractive(array $arguments): array
    {
        return $this->runCommand(
            array_merge(array('git'), $arguments),
            $this->repositoryRoot,
            array(
                'GIT_TERMINAL_PROMPT' => '0',
                'GCM_INTERACTIVE' => 'Never',
            )
        );
    }

    /**
     * Reads configured Git credential helpers.
     *
     * @return string[]
     */
    private function readCredentialHelpers(): array
    {
        $result = $this->runGit(array('config', '--get-all', 'credential.helper'));
        if (($result['exitCode'] ?? 1) !== 0) {
            return array();
        }

        $helpers = preg_split('/\r?\n/', trim((string) ($result['stdout'] ?? ''))) ?: array();
        return array_values(array_filter(array_map('trim', $helpers), static function (string $helper): bool {
            return $helper !== '';
        }));
    }

    /**
     * Runs a Git command inside the configured repository root.
     *
     * @param string[] $arguments
     * @return array<string, mixed>
     */
    private function runGit(array $arguments): array
    {
        return $this->runCommand(array_merge(array('git'), $arguments), $this->repositoryRoot);
    }

    /**
     * Runs a Git commit command with author overrides from configuration.
     *
     * @param string[] $arguments
     * @return array<string, mixed>
     */
    private function runGitCommit(array $arguments): array
    {
        $environment = array(
            'GIT_AUTHOR_NAME' => (string) ($this->config['authorName'] ?? 'WorldMesh CMS'),
            'GIT_AUTHOR_EMAIL' => (string) ($this->config['authorEmail'] ?? 'cms@example.invalid'),
            'GIT_COMMITTER_NAME' => (string) ($this->config['authorName'] ?? 'WorldMesh CMS'),
            'GIT_COMMITTER_EMAIL' => (string) ($this->config['authorEmail'] ?? 'cms@example.invalid'),
        );

        return $this->runCommand(array_merge(array('git'), $arguments), $this->repositoryRoot, $environment);
    }

    /**
     * Runs an external command and returns stdout, stderr, and exit code.
     *
     * @param string[] $command
     * @param array<string, string>|null $environment
     * @return array<string, mixed>
     */
    private function runCommand(array $command, string $workingDirectory, ?array $environment = null): array
    {
        $specification = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );

        if ($environment !== null) {
            $environment = $this->buildCommandEnvironment($environment);
        }

        $pipes = array();
        $process = @proc_open($command, $specification, $pipes, $workingDirectory, $environment);
        if (!is_resource($process)) {
            return array(
                'exitCode' => 1,
                'stdout' => '',
                'stderr' => 'Prozess konnte nicht gestartet werden.',
            );
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return array(
            'exitCode' => is_int($exitCode) ? $exitCode : 1,
            'stdout' => is_string($stdout) ? $stdout : '',
            'stderr' => is_string($stderr) ? $stderr : '',
        );
    }

    /**
     * Merges command-specific environment overrides with the current process environment.
     *
     * @param array<string, string> $overrides
     * @return array<string, string>
     */
    private function buildCommandEnvironment(array $overrides): array
    {
        $baseEnvironment = getenv();
        $environment = is_array($baseEnvironment) ? $baseEnvironment : array();

        foreach ($overrides as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $environment[$key] = (string) $value;
        }

        return $environment;
    }

    /**
     * Builds a normalized error payload with optional status data.
     *
     * @param array<string, mixed>|null $commandResult
     * @param array<string, mixed>|null $status
     * @return array<string, mixed>
     */
    private function errorResult(string $message, ?array $commandResult = null, ?array $status = null): array
    {
        $payload = array(
            'ok' => false,
            'message' => $message,
        );

        if ($commandResult !== null) {
            $details = trim((string) (($commandResult['stderr'] ?? '') !== '' ? $commandResult['stderr'] : ($commandResult['stdout'] ?? '')));
            if ($details !== '') {
                $payload['details'] = $details;
            }
        }

        if ($status !== null) {
            $payload['status'] = $status;
        }

        return $payload;
    }

    /**
     * Determines whether a repo path belongs to the managed editorial roots.
     */
    private function isManagedRepoPath(string $repoPath): bool
    {
        $repoPath = $this->normalizePath($repoPath);
        if ($repoPath === '') {
            return false;
        }

        foreach ($this->managedRepoPaths as $managedPath) {
            if ($repoPath === $managedPath || strpos($repoPath . '/', $managedPath . '/') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determines whether a repo path is eligible for browser-based text merges.
     */
    private function isMergeableRepoPath(string $repoPath): bool
    {
        $projectPath = $this->projectPathFromRepoPath($repoPath);
        return $projectPath !== '' && $this->isEditableProjectPath($projectPath);
    }

    /**
     * Determines whether a project path is an editable Markdown document.
     */
    private function isEditableProjectPath(string $projectPath): bool
    {
        $projectPath = $this->normalizePath($projectPath);
        if ($projectPath === '' || strtolower(pathinfo($projectPath, PATHINFO_EXTENSION)) !== 'md') {
            return false;
        }

        return strpos($projectPath, 'content/') === 0 || strpos($projectPath, 'cms/pages/') === 0;
    }

    /**
     * Converts a repo path into the preferred display path for the admin UI.
     */
    private function displayPathFromRepoPath(string $repoPath): string
    {
        $projectPath = $this->projectPathFromRepoPath($repoPath);
        return $projectPath !== '' ? $projectPath : $repoPath;
    }

    /**
     * Converts an absolute path into a repo-relative path.
     */
    private function repoPathFromAbsolute(string $absolutePath): string
    {
        $absolutePath = rtrim(str_replace('\\', '/', $absolutePath), '/');
        $root = rtrim($this->repositoryRoot, '/');
        if ($absolutePath === $root) {
            return '';
        }

        if (strpos($absolutePath, $root . '/') !== 0) {
            return '';
        }

        return ltrim(substr($absolutePath, strlen($root)), '/');
    }

    /**
     * Converts a repo-relative path into a base-path-relative project path.
     */
    private function projectPathFromRepoPath(string $repoPath): string
    {
        return $this->projectPathFromAbsolute($this->repositoryRoot . '/' . ltrim($this->normalizePath($repoPath), '/'));
    }

    /**
     * Converts an absolute path into a base-path-relative project path.
     */
    private function projectPathFromAbsolute(string $absolutePath): string
    {
        $absolutePath = rtrim(str_replace('\\', '/', $absolutePath), '/');
        $base = rtrim($this->basePath, '/');
        if ($absolutePath === $base) {
            return '';
        }

        if (strpos($absolutePath, $base . '/') !== 0) {
            return '';
        }

        return ltrim(substr($absolutePath, strlen($base)), '/');
    }

    /**
     * Resolves a relative path against the CMS base path.
     */
    private function resolvePath(string $path): string
    {
        $path = $this->normalizePath($path);
        if ($path === '') {
            return $this->basePath;
        }

        if (preg_match('/^[A-Za-z]:\//', $path) === 1 || strpos($path, '//') === 0) {
            return rtrim($path, '/');
        }

        return $this->basePath . '/' . ltrim($path, '/');
    }

    /**
     * Ensures that a directory exists before Git writes or session persistence.
     */
    private function ensureDirectory(string $directory): void
    {
        if ($directory === '' || is_dir($directory)) {
            return;
        }

        mkdir($directory, 0777, true);
    }

    /**
     * Normalizes a Git ref-like token for remote and branch inputs.
     */
    private function normalizeRefName(string $value): string
    {
        $value = trim(str_replace('\\', '/', $value));
        $value = preg_replace('/[^A-Za-z0-9._\\/-]+/', '-', $value) ?? $value;
        return trim($value, '/');
    }

    /**
     * Normalizes slash-separated project and repo paths.
     */
    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('/\/+/', '/', $path) ?? $path;
        $parts = array();
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                array_pop($parts);
                continue;
            }

            $parts[] = $part;
        }

        $prefix = preg_match('/^[A-Za-z]:$/', $parts[0] ?? '') === 1 ? array_shift($parts) . '/' : '';

        return $prefix . implode('/', $parts);
    }
}
