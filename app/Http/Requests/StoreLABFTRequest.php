<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLABFTRequest extends FormRequest
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
            'situation_mat' => 'nullable|string|max:50',
            'pays_residence' => 'required|string|max:100',
            'secteur' => 'required|string|max:255',
            'revenus_annuels' => 'required|string|max:100',
            'src_salaire' => 'nullable|boolean',
            'src_pro_liberal' => 'nullable|boolean',
            'src_foncier' => 'nullable|boolean',
            'src_dividendes' => 'nullable|boolean',
            'src_heritage' => 'nullable|boolean',
            'src_autre_check' => 'nullable|boolean',
            'src_autre' => 'required_if:src_autre_check,true|nullable|string|max:255',
            'origine_fonds' => 'required|string|max:255',
            'banque' => 'nullable|string|max:255',
            'num_compte' => 'nullable|string|max:100',
            'pays_compte' => 'nullable|string|max:100',
            'pays_risque' => 'required|string|in:Oui,Non',
            'secteur_sensible' => 'required|string|in:Oui,Non',
            'ppe' => 'required|string|in:Oui,Non',
            'ppe_detail' => 'required_if:ppe,Oui|nullable|string|max:500',
            'condamnation' => 'required|string|in:Oui,Non',
            'ack_lecture' => 'required|accepted',
            'ack_donnees' => 'required|accepted',
        ];
    }
}
