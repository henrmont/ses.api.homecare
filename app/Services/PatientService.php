<?php

namespace App\Services;

use App\Events\PatientEvent;
use App\Models\Archive;
use App\Models\Escort;
use App\Models\Patient;
use App\Models\PatientCare;
use App\Models\PatientCareEscort;
use App\Models\PatientInfo;
use App\Models\Report;
use App\Models\ReportAttachment;
use Exception;
use Illuminate\Database\Connection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PatientService
{
    /**
     * Conexão com o banco padrão (TFD).
     */
    private function homecare(): Connection
    {
        return DB::connection();
    }

    /**
     * Conexão com o banco Core.
     */
    private function core(): Connection
    {
        return DB::connection('core');
    }

    /**
     * Conexão com o banco Storage.
     */
    private function storage(): Connection
    {
        return DB::connection('storage');
    }

    /*
    |--------------------------------------------------------------------------
    | Gestão de Pacientes e Atendimentos
    |--------------------------------------------------------------------------
    */

    /**
     * Criar um novo paciente e seu atendimento (PatientCare).
     */
    public function createPatient(Request $request): JsonResponse
    {
        try {
            $this->homecare()->beginTransaction();
            $this->core()->beginTransaction();
            $this->storage()->beginTransaction();

            // 1. Limpa os campos de controle e arquivos do payload
            $patientData = $request->except([
                'observation', 
                'control_number', 
                'sigadoc',
                'file_cns', 
                'file_document', 
                'file_deficiency', 
                'file_address',
                'file_sigadoc'
            ]);

            // 2. Busca o paciente na conexão 'core'
            $patient = Patient::on('core')
                ->where(function ($query) use ($request) {
                    $query->where('cns', $request->cns)
                        ->orWhere('document', $request->document);
                })
                ->first();

            if ($patient) {
                $patient->update($patientData);
                $patientId = $patient->id;
            } else {
                // Insere diretamente via Query Builder na conexão 'core' e recupera o ID
                $patientId = $this->core()->table('patients')->insertGetId($patientData);
            }

            // 3. Insere os dados na tabela 'patient_cares' na conexão 'core'
            $patientCareId = $this->core()
                ->table('patient_cares')
                ->insertGetId([
                    'patient_id' => $patientId,
                    'module_id'  => auth()->user()->module_id,
                    'user_id'    => auth()->user()->id,
                ]);

            // Recria a variável $patient como uma instância do Model na conexão 'core'
            $patient = Patient::on('core')->find($patientId);

            // 4. Processa os anexos com a variável $patient recriada
            $this->processFileAttachments($patient, $request, [
                'file_cns'        => 'file_cns_id',
                'file_document'   => 'file_document_id',
                'file_deficiency' => 'file_deficiency_id',
                'file_address'    => 'file_address_id',
            ]);

            // 5. Cria o PatientInfo na conexão padrão (TFD)
            $patientInfo = PatientInfo::create([
                'patient_id'     => $patientId,
                'observation'    => $request->observation,
                'control_number' => $request->control_number,
                'sigadoc'        => $request->sigadoc,
            ]);

            $this->processFileAttachments($patientInfo, $request, [
                'file_sigadoc' => 'file_sigadoc_id',
            ]);

            // Commits
            $this->homecare()->commit();
            $this->core()->commit();
            $this->storage()->commit();

            return response()->json(['message' => 'Paciente cadastrado com sucesso.'], JsonResponse::HTTP_OK);
        } catch (Exception $e) {
            $this->homecare()->rollBack();
            $this->core()->rollBack();
            $this->storage()->rollBack();

            Log::error('Erro ao cadastrar paciente: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json(['message' => $e->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Atualizar dados cadastrais e anexos do paciente/atendimento.
     */
    public function updatePatient(PatientCare $patient_care, Request $request): JsonResponse
    {
        try {
            $this->homecare()->beginTransaction();
            $this->core()->beginTransaction();
            $this->storage()->beginTransaction();

            $patient = $patient_care->patient;
            $patient->update($request->except(['observation', 'control_number']));

            $this->processFileAttachments($patient, $request, [
                'file_cns'        => 'file_cns_id',
                'file_document'   => 'file_document_id',
                'file_deficiency' => 'file_deficiency_id',
                'file_address'    => 'file_address_id',
            ]);

            $patient_info = PatientInfo::query()
                ->updateOrCreate(
                    ['patient_id' => $patient_care->patient->id],
                    $request->only(['observation', 'control_number', 'sigadoc'])
                );

            $this->processFileAttachments($patient_info, $request, [
                'file_sigadoc' => 'file_sigadoc_id',
            ]);

            $this->homecare()->commit();
            $this->core()->commit();
            $this->storage()->commit();

            // broadcast(new PatientEvent($patient_care))->toOthers();

            return response()->json(['message' => 'Paciente atualizado com sucesso.'], JsonResponse::HTTP_OK);
        } catch (Exception $e) {
            $this->homecare()->rollBack();
            $this->core()->rollBack();
            $this->storage()->rollBack();

            Log::error('Erro ao atualizar paciente: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json(['message' => $e->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Alteração de Estado e Movimentações
    |--------------------------------------------------------------------------
    */

    /**
     * Arquivar atendimento do paciente.
     */
    public function archivePatient(PatientCare $patient_care): JsonResponse
    {
        try {
            $patient_care->update(['is_archived' => true]);

            return response()->json(['message' => 'Paciente arquivado com sucesso.'], JsonResponse::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erro ao arquivar paciente: ' . $e->getMessage());

            return response()->json(['message' => $e->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Retirar atendimento do arquivo e atribuir ao usuário autenticado.
     */
    public function movePatientFromArchive(PatientCare $patient_care): JsonResponse
    {
        try {
            $patient_care->update([
                'is_archived' => false,
                'user_id' => auth()->user()->id,
                'back_to_user' => 'Retirou do arquivo',
            ]);

            return response()->json(['message' => 'Paciente transferido para sua caixa.'], JsonResponse::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erro ao transferir paciente do arquivo: ' . $e->getMessage());

            return response()->json(['message' => $e->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Transferir atendimento do setor "Outros" para a caixa do usuário autenticado.
     */
    public function movePatientFromOthers(PatientCare $patient_care): JsonResponse
    {
        try {
            $patient_care->update([
                'user_id' => auth()->user()->id,
            ]);

            return response()->json(['message' => 'Paciente transferido para sua caixa.'], JsonResponse::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erro ao transferir paciente do setor outros: ' . $e->getMessage());

            return response()->json(['message' => $e->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Alternar validação do atendimento do paciente.
     */
    public function validatePatient(PatientCare $patient_care): JsonResponse
    {
        try {
            $patient_care->update(['is_valid' => !$patient_care->is_valid]);
            $statusText = $patient_care->is_valid ? 'validado' : 'invalidado';

            return response()->json(['message' => "Paciente {$statusText} com sucesso."], JsonResponse::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erro ao validar/invalidar paciente: ' . $e->getMessage());

            return response()->json(['message' => $e->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Finalizar retorno pendente do paciente.
     */
    public function finishBackPatient(PatientCare $patient_care): JsonResponse
    {
        try {
            $patient_care->update(['back_to_user' => null]);

            return response()->json(['message' => 'Paciente atualizado com sucesso.'], JsonResponse::HTTP_OK);
        } catch (Exception $e) {
            Log::error('Erro ao finalizar retorno do paciente: ' . $e->getMessage());

            return response()->json(['message' => $e->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Métodos Auxiliares Privados
    |--------------------------------------------------------------------------
    */

    /**
     * Processa o upload de arquivos para um determinado modelo.
     */
    private function processFileAttachments($model, Request $request, array $fileFields): void
    {
        $updates = [];

        foreach ($fileFields as $requestKey => $modelColumn) {
            if ($request->hasFile($requestKey)) {
                $updates[$modelColumn] = $this->storeArchive($request->file($requestKey));
            }
        }

        if (!empty($updates)) {
            $model->update($updates);
        }
    }

    /**
     * Armazena um arquivo como Base64 no banco de dados de storage.
     */
    private function storeArchive(UploadedFile $file): int
    {
        $fileContents = file_get_contents($file->getRealPath());
        $base64String = base64_encode($fileContents);
        $mimeType = $file->getClientMimeType();
        $dataUri = "data:{$mimeType};base64,{$base64String}";

        $archive = Archive::on('storage')->create(['archive' => $dataUri]);

        return $archive->id;
    }
}