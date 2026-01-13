<?php
// batch_generate.php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/helpers.php';
require_once 'includes/renderer.php';

require_login();
ini_set('max_execution_time', 300); // Allow 5 minutes

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}

$batch_id = filter_input(INPUT_POST, 'batch_id', FILTER_VALIDATE_INT);
if (!$batch_id) redirect('dashboard.php');

// Fetch Batch info
$stmt = $pdo->prepare("SELECT b.*, t.image_path, t.settings_json 
                       FROM batches b 
                       JOIN templates t ON b.template_id = t.id 
                       WHERE b.id = ? AND b.user_id = ?");
$stmt->execute(array($batch_id, current_user_id()));
$batch = $stmt->fetch();

if (!$batch) {
    set_flash('error', 'Batch not found.');
    redirect('dashboard.php');
}

// Update status to processing
$stmt = $pdo->prepare("UPDATE batches SET status = 'processing' WHERE id = ?");
$stmt->execute(array($batch_id));

try {
    // Get Rows
    $stmt = $pdo->prepare("SELECT * FROM batch_rows WHERE batch_id = ?");
    $stmt->execute(array($batch_id));
    $rows = $stmt->fetchAll();

    $settings = json_decode($batch['settings_json'], true);
    $layers = isset($settings['layers']) ? $settings['layers'] : array();
    $template_path = $batch['image_path'];

    // Output Dirs
    $batch_dir_name = 'batch_' . $batch_id;
    $output_base_dir = 'uploads/outputs/' . $batch_dir_name . '/';
    if (!is_dir($output_base_dir)) mkdir($output_base_dir, 0777, true);

    $files_to_zip = array();
    $generated_count = 0;

    foreach ($rows as $index => $row) {
        $row_data = json_decode($row['data_json'], true);
        
        // Name the file based on 'name' field if available, else index
        $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '', isset($row_data['name']) ? $row_data['name'] : 'cert');
        $filename = $safe_name . '_' . ($index + 1) . '.png';
        $output_path = $output_base_dir . $filename;

        if (render_certificate($template_path, $output_path, $layers, $row_data)) {
            $files_to_zip[] = array(
                'local_path' => $output_path,
                'zip_name' => $filename
            );
            
            // Update row output path
            $up_stmt = $pdo->prepare("UPDATE batch_rows SET output_path = ? WHERE id = ?");
            $up_stmt->execute(array($output_path, $row['id']));
            $generated_count++;
        }
    }

    if ($generated_count === 0) {
        throw new Exception("No certificates were generated.");
    }

    // Create Zip
    $zip_filename = 'batch_' . $batch_id . '_' . time() . '.zip';
    $zip_path = 'uploads/zips/' . $zip_filename;
    
    // Ensure zip dir
    if (!is_dir(dirname($zip_path))) mkdir(dirname($zip_path), 0777, true);

    $zip = new ZipArchive();
    if ($zip->open($zip_path, ZipArchive::CREATE) !== TRUE) {
        throw new Exception("Could not create ZIP file.");
    }

    foreach ($files_to_zip as $file) {
        $zip->addFile($file['local_path'], $file['zip_name']);
    }
    $zip->close();

    // Update Batch Status
    $stmt = $pdo->prepare("UPDATE batches SET status = 'done', zip_path = ? WHERE id = ?");
    $stmt->execute(array($zip_path, $batch_id));

    set_flash('success', "Batch generated successfully! $generated_count certificates created.");

} catch (Exception $e) {
    $stmt = $pdo->prepare("UPDATE batches SET status = 'failed', error_message = ? WHERE id = ?");
    $stmt->execute(array($e->getMessage(), $batch_id));
    set_flash('error', 'Batch failed: ' . $e->getMessage());
}

redirect('dashboard.php');
