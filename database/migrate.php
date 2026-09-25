<?php
// CLI-only migration runner. Run from the project root: php database/migrate.php
if(PHP_SAPI!=='cli'){http_response_code(403);exit("CLI only.\n");}
require_once __DIR__.'/../shared/config.php';
$db=getDB();
$lock=(int)$db->query("SELECT GET_LOCK('o2o_tradition_migrations',10)")->fetchColumn();
if($lock!==1){fwrite(STDERR,"Could not acquire migration lock. Another migration may be running.\n");exit(1);}
register_shutdown_function(function() use ($db){try{$db->query("SELECT RELEASE_LOCK('o2o_tradition_migrations')");}catch(Throwable $e){}});
$db->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration VARCHAR(255) NOT NULL UNIQUE,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");
$dir=__DIR__.'/migrations';
$files=glob($dir.'/*.sql')?:[];
sort($files,SORT_NATURAL);
$applied=$db->query("SELECT migration FROM schema_migrations ORDER BY migration")->fetchAll(PDO::FETCH_COLUMN);
$appliedMap=array_fill_keys($applied,true);
$pending=array_values(array_filter($files,fn($f)=>!isset($appliedMap[basename($f)])));
$mode=$argv[1]??'';
if($mode==='--status'||$mode==='--dry-run'){
    echo "Applied migrations: ".count($applied)."\n";
    foreach($files as $file){
        $name=basename($file);
        echo (isset($appliedMap[$name])?'APPLIED ':'PENDING ').$name."\n";
    }
    if($mode==='--dry-run')echo "No changes made.\n";
    exit(0);
}
if($mode!==''){$allowed=['--status','--dry-run'];fwrite(STDERR,"Unknown option. Use --status or --dry-run.\n");exit(2);}
if(!$pending){echo "No pending migrations.\n";exit(0);}
foreach($pending as $file){
    $name=basename($file);
    echo "Applying {$name}... ";
    $sql=file_get_contents($file);
    if($sql===false)throw new RuntimeException("Cannot read {$name}");
    try{
        $db->exec($sql);
        $st=$db->prepare("INSERT INTO schema_migrations (migration) VALUES (?)");
        $st->execute([$name]);
        echo "OK\n";
    }catch(Throwable $e){
        fwrite(STDERR,"FAILED\n".$e->getMessage()."\n");
        exit(1);
    }
}
echo "Applied ".count($pending)." migration(s).\n";
