<?php
// CLI-only migration runner. Run from the project root: php database/migrate.php
if(PHP_SAPI!=='cli'){http_response_code(403);exit("CLI only.\n");}
require_once __DIR__.'/../shared/config.php';
$db=getDB();
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
