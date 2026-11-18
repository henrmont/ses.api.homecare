<?php

namespace App\Http\Controllers;

use App\Models\Cid;
use App\Models\Escort;
use App\Models\Patient;
use App\Models\PatientCare;
use App\Models\PatientCareEscort;
use App\Models\Report;
use App\Models\ReportAttachment;
use App\Services\PatientService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected PatientService $patientService
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Gestão de Pacientes / Atendimentos (TFD)
    |--------------------------------------------------------------------------
    */

    /**
     * Listar pacientes/atendimentos ativos do TFD.
     */
    public function getPatients(): JsonResponse
    {
        $this->authorize('homecare/paciente listar');

        $patientCares = PatientCare::query()
            ->homecare()
            ->where('is_archived', false)
            ->with('patient.patientInfo', 'user.professional')
            ->latest('id')
            ->get();

        return response()->json($patientCares, JsonResponse::HTTP_OK);
    }

    /**
     * Listar pacientes/atendimentos arquivados do TFD.
     */
    public function getArchivePatients(): JsonResponse
    {
        $this->authorize('homecare/paciente listar');

        $patientCares = PatientCare::query()
            ->homecare()
            ->where('is_archived', true)
            ->with('patient.patientInfo', 'user.professional')
            ->latest('id')
            ->get();

        return response()->json($patientCares, JsonResponse::HTTP_OK);
    }

    /**
     * Criar um novo paciente/atendimento.
     */
    public function createPatient(Request $request)
    {
        $this->authorize('homecare/paciente criar');

        return $this->patientService->createPatient($request);
    }

    /**
     * Atualizar dados de um paciente/atendimento.
     */
    public function updatePatient(PatientCare $patient_care, Request $request)
    {
        $this->authorize('homecare/paciente atualizar');

        return $this->patientService->updatePatient($patient_care, $request);
    }

    /**
     * Arquivar um atendimento de paciente.
     */
    public function archivePatient(PatientCare $patient_care)
    {
        $this->authorize('homecare/paciente atualizar');

        return $this->patientService->archivePatient($patient_care);
    }

    /**
     * Desalocar/remover um atendimento do arquivo.
     */
    public function movePatientFromArchive(PatientCare $patient_care)
    {
        $this->authorize('homecare/paciente atualizar');

        return $this->patientService->movePatientFromArchive($patient_care);
    }

    /**
     * Movimentar um atendimento do setor "Outros".
     */
    public function movePatientFromOthers(PatientCare $patient_care)
    {
        $this->authorize('homecare/paciente atualizar');

        return $this->patientService->movePatientFromOthers($patient_care);
    }

    /**
     * Validar status de um atendimento.
     */
    public function validatePatient(PatientCare $patient_care)
    {
        $this->authorize('homecare/paciente validar');

        return $this->patientService->validatePatient($patient_care);
    }

    /**
     * Finalizar o retorno de uma solicitação no contexto de pareceres.
     */
    public function finishBackPatient(PatientCare $patient_care)
    {
        $this->authorize('homecare/paciente atualizar');

        return $this->patientService->finishBackPatient($patient_care);
    }

    /*
    |--------------------------------------------------------------------------
    | Consultas Diretas (Busca e Validação)
    |--------------------------------------------------------------------------
    */

    /**
     * Buscar dados de paciente pelo número do CNS e indicar se já possui cadastro no TFD.
     */
    public function getPatientCns(string $cns): JsonResponse
    {
        $this->authorize('homecare/paciente listar');

        $cleanCns = preg_replace('/\D/', '', $cns);

        $patient = Patient::query()
            ->withExists(['patientCares as exists_in_tfd' => fn ($q) => $q->homecare()])
            ->where('cns', $cleanCns)
            ->first();

        return response()->json($patient, JsonResponse::HTTP_OK);
    }

    /**
     * Buscar dados de paciente pelo número do documento (CPF/RG) e indicar se já possui cadastro no TFD.
     */
    public function getPatientDocument(string $document): JsonResponse
    {
        $this->authorize('homecare/paciente listar');

        $cleanDocument = preg_replace('/\D/', '', $document);

        $patient = Patient::query()
            ->withExists(['patientCares as exists_in_tfd' => fn ($q) => $q->homecare()])
            ->where('document', $cleanDocument)
            ->first();

        return response()->json($patient, JsonResponse::HTTP_OK);
    }
    
}