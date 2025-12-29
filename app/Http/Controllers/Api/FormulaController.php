<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FormulaManagementService;
use App\Services\FormulaCostService;
use App\Services\FormulaAnalysisService;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Exception;

class FormulaController extends Controller
{
    protected FormulaManagementService $managementService;
    protected FormulaCostService $costService;
    protected FormulaAnalysisService $analysisService;
    protected ActivityLogService $activityLog;

    public function __construct(
        FormulaManagementService $managementService,
        FormulaCostService $costService,
        FormulaAnalysisService $analysisService,
        ActivityLogService $activityLog
    ) {
        $this->managementService = $managementService;
        $this->costService = $costService;
        $this->analysisService = $analysisService;
        $this->activityLog = $activityLog;
    }

    /**
     * =========================
     * CREATE FORMULA
     * =========================
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'product_id' => ['required', 'integer', 'exists:products,id'],
                'nama_formula' => ['required', 'string'],
                'catatan' => ['nullable', 'string'],
                'is_active' => ['nullable', 'boolean'],
                'materials' => ['required', 'array', 'min:1'],
                'materials.*.material_id' => ['required', 'integer', 'exists:materials,id'],
                'materials.*.qty' => ['required', 'numeric', 'min:0.0001'],
            ]);

            $formula = $this->managementService->createFormula($validated);

            $this->activityLog->log(
                auth()->id(),
                'create_formula',
                [
                    'formula_id' => $formula->id,
                    'nama_formula' => $formula->nama_formula,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Formula berhasil dibuat',
                'data' => $formula,
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors(),
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * =========================
     * UPDATE FORMULA
     * =========================
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'nama_formula' => ['sometimes', 'string'],
                'catatan' => ['sometimes', 'nullable', 'string'],
                'is_active' => ['sometimes', 'boolean'],
                'materials' => ['sometimes', 'array', 'min:1'],
                'materials.*.material_id' => ['required_with:materials', 'integer', 'exists:materials,id'],
                'materials.*.qty' => ['required_with:materials', 'numeric', 'min:0.0001'],
            ]);

            $formula = $this->managementService->updateFormula($id, $validated);

            $this->activityLog->log(
                auth()->id(),
                'update_formula',
                [
                    'formula_id' => $formula->id,
                    'nama_formula' => $formula->nama_formula,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Formula berhasil diperbarui',
                'data' => $formula,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * =========================
     * DELETE FORMULA
     * =========================
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->managementService->deleteFormula($id);

            $this->activityLog->log(
                auth()->id(),
                'delete_formula',
                ['formula_id' => $id]
            );

            return response()->json([
                'success' => true,
                'message' => 'Formula berhasil dihapus',
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * =========================
     * TOGGLE ACTIVE STATUS
     * =========================
     */
    public function toggleActive(int $id): JsonResponse
    {
        $formula = $this->managementService->toggleActive($id);

        $this->activityLog->log(
            auth()->id(),
            'toggle_formula_status',
            [
                'formula_id' => $formula->id,
                'is_active' => $formula->is_active,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $formula,
        ]);
    }

    /**
     * =========================
     * COST & MATERIAL NEEDS
     * =========================
     */
    public function cost(Request $request, int $id): JsonResponse
    {
        $qty = (float) $request->get('qty', 100);

        $data = $this->costService->calculateProductionCost($id, $qty);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * =========================
     * FORMULA EFFICIENCY
     * =========================
     */
    public function efficiency(int $id): JsonResponse
    {
        $data = $this->analysisService->getFormulaEfficiency($id);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * =========================
     * COMPARE FORMULAS
     * =========================
     */
    public function compare(Request $request, int $productId): JsonResponse
    {
        $qty = (float) $request->get('qty', 100);

        $data = $this->analysisService->compareFormulas($productId, $qty);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}