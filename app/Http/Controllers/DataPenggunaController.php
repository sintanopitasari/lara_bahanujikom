<?php

namespace App\Http\Controllers;

//LOAD MODEL
use App\Models\DtPengguna;
use App\Models\User;
//PACKAGE BAWAAN
use Illuminate\Http\Request;
use File;
use App\Imports\ImportDataPenggunaClass;
use App\Exports\DataPenggunaExportView;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Validator;
use Excel;

//LOAD PACKAGE PDF
use PDF;

//LOAD HELPER
use Tanggal;


class DataPenggunaController extends Controller
{
    public function __construct(){
        $this->Tanggal = new Tanggal();
    }

    // Menampilkan Halaman Data Pengguna
    public function index(Request $request)
    {
        // Menampung sebuah Request
        $f1=$request->input('f1');
        $data = DtPengguna::select('*');

        // Kondisi kalo var $f1 ada isinya
        if($f1){
            $data->where('isrole','=',''.$f1.'')->get();
        }

        // Kondisi apabila gak ada isi dari var $f1
        $data = $data->get();
        return view('data_pengguna.index',['data'=>$data]);
    }

    // Menampilkan Halaman Input data Pengguna
    public function input(Request $request)
    {
        return view("data_pengguna.formInput");
    }

    // Proses create Data Pengguna
    public function create(Request $request)
    {

        //DECLARE REQUEST
        // $hakakses = $request->input('isrole');
        $namerole = $request->input('namerole'); 
        $name = $request->input('name'); 
        $email = $request->input('email');
        $password = $request->input('password'); 
        $img = $request->file('img'); 

        // Membuat Kondisi 
        if ($namerole === 'administrator') {
            $isrole = 1; 
        } else {
            $isrole = 2; 
        }
        //COSTUM REQUEST
        // $namerole = null;

        //Validasi dari input yang masuk dengan ketentuan tertentu
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            // 'isrole' => 'required|numeric',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|max:80|min:8',
            'img' => 'nullable|image|mimes:jpg,png,jpeg|max:2000',
        ]);

        // Membuat kondisi apabila dari proses validasi ada yang salah maka tampilkan message
        if ($validator->fails()) {
            $errormessage='';
            foreach ($validator->errors()->all() as $message) {
                $errormessage.='<li>'.$message.'</li>';
            }
            return back()
            ->with('failed','Harap periksa kembali inputan!. <ul>'.$errormessage.'</ul>')->withInput();
        }
        if (isset($img)) {
            $imageName = ''.date('YmdHis').'-'.uniqid().'.'.$img->getClientOriginalExtension();
            $destinationPath = 'images/user/';
            //CEK FILE IN FOLDER
            if (File::exists(public_path($destinationPath.$imageName))) {
                File::delete(public_path($destinationPath.$imageName));
            }
            // UPLOAD TO THE DESTINATION PATH ($dir_path) IN PUBLIC FOLDER
            $img->move($destinationPath, $imageName);
            $post['img'] = $imageName;
        } else {
            $post['img'] = null; 
        }
        

        //  Fungsi ini bertanggung jawab untuk membuat dan menyimpan data pengguna baru 
        try {
            $post['name'] = $name;
            $post['email_verified_at'] = now();
            $post['password'] = Hash::make($password); 
            $post['remember_token'] = Str::random(10); 
            $post['isrole'] = $isrole;
            $post['namerole'] = $namerole;
            $post['email'] = $email;
			$after = DtPengguna::create($post); 
            $data  = DtPengguna::where('id','=',$after->id)->first();
            return redirect() 
            ->route('data_pengguna', ['id' => $data->id])
            ->with('success', 'Data berhasil disimpan');
		}
		catch(Exception $e){
			return back()
            ->withInput()
            ->with('error','Gagal memproses!');
		}
    }
    
    // Menampilkan halaman edit data pengguna
    public function edit($id)
    {
        // GET THE DATA BASED ON ID
        $data = DtPengguna::find($id); 
        // CHECK IS DATA FOUND
        if (!$data) { 
            // DATA NOT FOUND
            return back()
                ->withInput()
                ->with('error', 'item not found!');
        }
        return view('data_pengguna.formEdit', compact('data','id'));
    }

    // Proses untuk update data
    public function update($id,Request $request)
    {
        // dd($request); -> Cek data yang masuk

        if ((int) $id < 1) { 
            return redirect()
                ->route('data_pengguna')
                ->with('error', 'item not found!'); 
        }
        // $isrole=auth()->user()->isrole;

       $namerole = $request->input('namerole'); 
       $name = $request->input('name'); 
       $email = $request->input('email');
       $password = $request->input('password'); 
       $img = $request->file('img'); 

       // Membuat Kondisi 
       if ($namerole === 'administrator') {
           $isrole = 1; 
       } else {
           $isrole = 2; 
       }
        //COSTUM REQUEST
        // $namerole = null;


        // GET THE DATA BASED ON ID
        $data = DtPengguna::find($id);
        // CHECK IS DATA FOUND
        if (!$data) {
            // DATA NOT FOUND
            return back()
                ->withInput()
                ->with('error', 'item not found!');
        }
        $img_b=$old->img??null;
        $id_b=$old->id??'';
        if (!$img) {
            $img_b = $data->img ?? null;
        }

        //Validasi dari input yang masuk dengan ketentuan tertentu
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            // 'isrole' => 'required|numeric',
            'email' => 'nullable|email'.$id_b,
            'password' => 'nullable|max:80|min:8',
            'img' => 'nullable|image|mimes:jpg,png,jpeg|max:2000',
        ]);

        // Mengecek apakah ada error dari validasi di atas
        if ($validator->fails()) {
            $errormessage='';

            foreach ($validator->errors()->all() as $message) {
                $errormessage.='<li>'.$message.'</li>';
            }
            return back()
            ->with('failed','Harap periksa kembali inputan!. <ul>'.$errormessage.'</ul>')->withInput();
        }

        // Kondisi mengecek apakah ada file baru
        if (isset($img)) {
            // Jika ada file gambar baru diunggah
            $imageName = ''.date('YmdHis').'-'.uniqid().'.'.$img->getClientOriginalExtension();
            $destinationPath = 'images/user/';
            if (File::exists(public_path($destinationPath.$img_b))) {
                File::delete(public_path($destinationPath.$img_b));
            }
            // UPLOAD TO THE DESTINATION PATH ($dir_path) IN PUBLIC FOLDER
            $img->move($destinationPath, $imageName);
            $post['img'] = $imageName;
        } else {
            $post['img'] = $img_b;
        }


        try { 
            
            if($password){ 
                $post['password'] = Hash::make($password);
            }
            // $post['email_verified_at'] = now();
            $post['name'] = $name;
            $post['email'] = $email;
            $post['isrole'] = $isrole;
            $post['namerole'] = $namerole;
           
            // $post['remember_token'] = Str::random(10);
            DtPengguna::where('id', $id)->update($post);
            return redirect()
            ->route('data_pengguna.edit', ['id' => $id]) 
            ->with('success', 'Data berhasil disimpan'); 
		}
		catch(Exception $e){
			return back() 
            ->withInput()
            ->with('error','Gagal memproses!'); 
		}
    
    }

    // Proses Delete Data
    public function destroy($id)
    {
        if ((int) $id < 1) {
            return redirect()
                ->route('data_barang')
                ->with('error', 'item not found!');
        }

        $db = DtPengguna::where('id', $id); 
        $cek = $db->count(); 
        $data = $db->first();
        $file_b = $data->img??''; 

        // Proses delete data
        try {
            // Kondisi 
            if ($cek) {

                // Jika kondisi $cek terpenuhi
                if ($file_b) { 
                    $destinationPath = 'images/user/';
                    if (File::exists(public_path($destinationPath.$file_b))) {
                        File::delete(public_path($destinationPath.$file_b));
                    }
                }

                // Proses Delete Data
                $db->delete();
            }
            // Kalo berhasil arahkan ke halaman data pengguna
            return redirect()
            ->route('data_pengguna')
            ->with('success', 'Data berhasil dihapus');
        }

        // Kalo proses gagal
        catch(Exception $e){
            // ERROR
			return back() 
            ->withInput()
            ->with('error','Gagal memproses!'); 
		}
    }

    public function export_excel(Request $request)
    {
        $f1=$request->input('f1');
        //QUERY untuk memanggil data dari DB melalui Model DtPengguna
        $data = DtPengguna::select('*');
        
        // Kondisi apabila var $f1 ada isinya
        if($f1){
            $data->where('isrole','=',''.$f1.'')->get(); 
        }
        $data = $data->get(); 

       
        $export = new DataPenggunaExportView($data);
        
        // Set nama file export nya
        $filename = date('YmdHis') . '_data_pengguna';
        
        // Download file excel
        return Excel::download($export, $filename . '.xlsx');
    }

    // Proses export PDF
    public function export_pdf(Request $request)
    {
        $f1=$request->input('f1');
        //QUERY untuk memanggil data dari DB melalui Model DtPengguna
        $data = DtPengguna::select('*');

          
        if($f1){
            $data->where('isrole','=',''.$f1.'')->get();  
        }
        $data = $data->get(); 

        $pdf = PDF::loadview('data_pengguna.exportPdf', ['data'=>$data]); 
        $pdf->setPaper('a4', 'portrait'); 
        $pdf->setOption(['dpi' => 150, 'defaultFont' => 'sans-serif']);
        $filename = date('YmdHis') . '_data_pengguna';  
        return $pdf->download($filename.'.pdf'); 
    }

    public function import_excel(Request $request)
    {
        $file = $request->file('file');

        //VALIDATION FORM
        $request->validate([
            'file' => 'required|mimes:csv,xls,xlsx'
        ]);

        try {
            if($file){
                // IMPORT DATA
                $import = new ImportDataPenggunaClass;
                Excel::import($import, $file);
                
                // SUCCESS
                $notimportlist="";
                if ($import->listgagal) {
                    $notimportlist.="<hr> Not Register : <br> {$import->listgagal}";
                }
                return redirect()
                ->route('data_pengguna')
                ->with('success', 'Import Data berhasil,<br>
                Size '.$file->getSize().', File extention '.$file->extension().',
                Insert '.$import->insert.' data, Update '.$import->edit.' data,
                Failed '.$import->gagal.' data, <br> '.$notimportlist.'');

            } else {
                // ERROR
                return back()
                ->withInput()
                ->with('error','Gagal memproses!');
            }
            
		}
		catch(Exception $e){
			// ERROR
			return back()
            ->withInput()
            ->with('error','Gagal memproses!');
		}

    }
   
}