<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreKYCRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'civ' => 'required|string|in:M.,Mme',
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'situation_mat' => 'required|string|max:50',
            'nat' => 'required|string|max:255',
            'dob' => 'required|date|before_or_equal:-21 years',
            'lieu_naiss' => 'required|string|max:255',
            'adresse' => 'required|string|max:255',
            'tel' => 'required|string|max:50',
            'email' => 'required|email|max:255',
            'piece' => 'required|string|in:CNI,Passeport,Carte Résident',
            'num_piece' => 'required|string|max:100',
            'expiration_piece' => 'required|date|after:today',
            'profession' => 'nullable|string|max:255',
            'employeur' => 'nullable|string|max:255',
            'piece_recto' => 'nullable|string',
            'piece_verso' => 'nullable|string',
            'selfie_live' => 'nullable|string',
            'face_match_score' => 'nullable|numeric|min:0|max:100',
            'face_verified' => 'nullable|boolean',
            'ocr_score' => 'nullable|numeric|min:0|max:100',
            'ocr_nom_match' => 'nullable|boolean',
            'ocr_prenom_match' => 'nullable|boolean',
            'ocr_dob_match' => 'nullable|boolean',
            'ocr_num_piece_match' => 'nullable|boolean',
            'ocr_snippet' => 'nullable|string',
            'doc_piece_identite' => 'nullable|string',
            'doc_justificatif_domicile' => 'nullable|string',
            'doc_photo' => 'nullable|string',
            'doc_origine_fonds' => 'nullable|string',
            'verification_timestamp' => 'nullable|string',
            'identity_audit_status' => 'nullable|string|max:50',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'dob.before_or_equal' => 'Vous devez être âgé d\'au moins 21 ans pour procéder à l\'onboarding.',
            'expiration_piece.after' => 'La date d\'expiration doit être supérieure à la date du jour.',
        ];
    }
}
