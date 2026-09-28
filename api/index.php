<?php

header('Content-Type: application/json; charset=utf-8');

$dataFile = dirname(__DIR__) . '/data/nilai.json';


// ======================================================
// FUNGSI RESPONSE
// ======================================================

function responseJson($data, $statusCode = 200)
{
    http_response_code($statusCode);

    echo json_encode(
        $data,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
    );

    exit;
}


// ======================================================
// SIAPKAN FILE DATA
// ======================================================

if (!file_exists($dataFile)) {

    file_put_contents(
        $dataFile,
        json_encode([], JSON_PRETTY_PRINT)
    );
}


$data = json_decode(
    file_get_contents($dataFile),
    true
);


// Jika JSON kosong/rusak
if (!is_array($data)) {
    $data = [];
}


// ======================================================
// METHOD & URL
// ======================================================

$method = $_SERVER['REQUEST_METHOD'];

$path = parse_url(
    $_SERVER['REQUEST_URI'],
    PHP_URL_PATH
);


// Hapus slash di awal dan akhir
$path = trim($path, '/');


// Pecah URL
$parts = explode('/', $path);


// Cari ID jika ada
$id = null;

if (
    isset($parts[2]) &&
    is_numeric($parts[2])
) {

    $id = (int) $parts[2];

}


// ======================================================
// GET /api/nilai
// ======================================================

if ($method === 'GET' && $id === null) {

    $result = $data;


    // ==================================================
    // FILTER KETERANGAN
    // Contoh:
    // /api/nilai?keterangan=Lulus
    // ==================================================

    if (isset($_GET['keterangan'])) {

        $filter = strtolower(
            trim($_GET['keterangan'])
        );


        $result = array_values(
            array_filter(
                $result,
                function ($item) use ($filter) {

                    return strtolower(
                        $item['keterangan']
                    ) === $filter;

                }
            )
        );

    }


    responseJson([

        'status' => 'success',

        'total' => count($result),

        'data' => $result

    ]);

}


// ======================================================
// GET /api/nilai/{id}
// ======================================================

if ($method === 'GET' && $id !== null) {

    foreach ($data as $item) {

        if ((int) $item['id'] === $id) {

            responseJson([

                'status' => 'success',

                'total' => 1,

                'data' => [$item]

            ]);

        }

    }


    responseJson([

        'status' => 'error',

        'message' => 'Data dengan ID tersebut tidak ditemukan'

    ], 404);

}


// ======================================================
// POST /api/nilai
// ======================================================

if ($method === 'POST') {


    // Ambil JSON dari request
    $input = json_decode(
        file_get_contents('php://input'),
        true
    );


    // Jika JSON tidak valid
    if (!is_array($input)) {

        responseJson([

            'status' => 'error',

            'message' => 'Format JSON tidak valid'

        ], 400);

    }


    // ==================================================
    // CEK FIELD WAJIB
    // ==================================================

    $requiredFields = [

        'nim',

        'nama',

        'mata_kuliah',

        'nilai'

    ];


    foreach ($requiredFields as $field) {

        if (
            !isset($input[$field]) ||
            $input[$field] === ''
        ) {

            responseJson([

                'status' => 'error',

                'message' =>
                    "Field '$field' wajib diisi"

            ], 400);

        }

    }


    // ==================================================
    // VALIDASI NIM
    // Harus tepat 8 angka
    // ==================================================

    if (
        !preg_match(
            '/^[0-9]{10}$/',
            $input['nim']
        )
    ) {

        responseJson([

            'status' => 'error',

            'message' =>
                'NIM harus terdiri dari tepat 10 digit angka'

        ], 400);

    }


    // ==================================================
    // VALIDASI NILAI
    // ==================================================

    if (
        !is_numeric($input['nilai']) ||
        $input['nilai'] < 0 ||
        $input['nilai'] > 100
    ) {

        responseJson([

            'status' => 'error',

            'message' =>
                'Nilai harus berupa angka 0 sampai 100'

        ], 400);

    }


    $nilai = (int) $input['nilai'];


    // ==================================================
    // KETERANGAN
    // ==================================================

    if ($nilai >= 60) {

        $keterangan = 'Lulus';

    } else {

        $keterangan = 'Tidak Lulus';

    }


    // ==================================================
    // GRADE
    // ==================================================

    if ($nilai >= 85) {

        $grade = 'A';

    } elseif ($nilai >= 75) {

        $grade = 'B';

    } elseif ($nilai >= 65) {

        $grade = 'C';

    } elseif ($nilai >= 50) {

        $grade = 'D';

    } else {

        $grade = 'E';

    }


    // ==================================================
    // BUAT ID OTOMATIS
    // ==================================================

    if (count($data) === 0) {

        $newId = 1;

    } else {

        $ids = array_column(
            $data,
            'id'
        );

        $newId = max($ids) + 1;

    }


    // ==================================================
    // DATA BARU
    // ==================================================

    $newData = [

        'id' => $newId,

        'nim' => $input['nim'],

        'nama' => $input['nama'],

        'mata_kuliah' =>
            $input['mata_kuliah'],

        'nilai' => $nilai,

        'keterangan' =>
            $keterangan,

        'grade' => $grade

    ];


    // Tambahkan ke array
    $data[] = $newData;


    // Simpan ke JSON
    $saved = file_put_contents(

        $dataFile,

        json_encode(
            $data,
            JSON_PRETTY_PRINT |
            JSON_UNESCAPED_UNICODE
        )

    );


    if ($saved === false) {

        responseJson([

            'status' => 'error',

            'message' =>
                'Gagal menyimpan data'

        ], 500);

    }


    // ==================================================
    // RESPONSE BERHASIL
    // ==================================================

    responseJson([

        'status' => 'success',

        'message' =>
            'Data berhasil ditambahkan',

        'data' => $newData

    ], 201);

}


// ======================================================
// METHOD TIDAK DIDUKUNG
// ======================================================

responseJson([

    'status' => 'error',

    'message' =>
        'Method tidak diizinkan'

], 405);
