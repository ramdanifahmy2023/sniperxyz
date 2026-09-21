<?php

class Passcode {
  public static function generate($accountId) {
    // Gunakan format waktu hingga satuan menit (TahunBulanTanggalJamMenit)
    // Dengan begini, angkanya hanya akan berubah saat berganti menit (detik diabaikan)
    $timePerMinute = date('YmdHi');
    $payload = $accountId . '-' . $timePerMinute;
    
    // Hasilkan angka unik dari gabungan tersebut
    $uniqueHash = abs(crc32($payload));
    
    // Pastikan selalu 8 digit
    return substr(str_pad($uniqueHash, 8, '0', STR_PAD_LEFT), 0, 8);
  }

  // Fungsi untuk mencari accounts.id berdasarkan Passcode
  public static function verify($passcode) {
    $db = new Database();
    $db->query('SELECT id FROM accounts');
    $accounts = $db->getAll();

    // Toleransi waktu: Menit saat ini dan 1 menit sebelumnya (untuk mengantisipasi delay jaringan)
    $validTimes = [
      date('YmdHi'), 
      date('YmdHi', time() - 60)
    ];

    foreach ($accounts as $account) {
      $accountId = $account['id'];
      
      foreach ($validTimes as $timePerMinute) {
        $payload = $accountId . '-' . $timePerMinute;
        $uniqueHash = abs(crc32($payload));
        $generatedCode = substr(str_pad($uniqueHash, 8, '0', STR_PAD_LEFT), 0, 8);
        
        if ($generatedCode === (string)$passcode) {
          return $accountId; // Cocok! Kembalikan ID akun
        }
      }
    }

    return false; // Tidak ada akun yang cocok
  }
}
