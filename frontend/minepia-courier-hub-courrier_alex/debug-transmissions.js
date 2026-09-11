// Debug script pour tester l'API transmissions
import { getTransmissions, convertTransmissionResponseToCourrierResponse } from './src/api/transmissionsApi.js';

async function debugTransmissions() {
  try {
    console.log('🧪 Test de l\'API transmissions...');
    
    // Test 1: Récupération de toutes les transmissions
    const filters = {
      page: 1,
      limit: 20,
      order_by: 'DESC',
      is_delete: false
    };
    
    console.log('📋 Récupération des transmissions avec filtres:', filters);
    const transmissionResponse = await getTransmissions(filters);
    
    console.log('📊 Réponse transmissions brute:', {
      total: transmissionResponse.total,
      count: transmissionResponse.data.length,
      page: transmissionResponse.page,
      totalPages: transmissionResponse.totalPages
    });
    
    // Test 2: Conversion vers format CourrierItem
    console.log('🔄 Conversion vers format CourrierItem...');
    const courrierResponse = convertTransmissionResponseToCourrierResponse(transmissionResponse);
    
    console.log('✅ Réponse convertie:', {
      total: courrierResponse.total,
      count: courrierResponse.data.length,
      page: courrierResponse.page,
      totalPages: courrierResponse.totalPages
    });
    
    console.log('📋 Premier élément converti:', courrierResponse.data[0]);
    
    console.log('🎉 Test réussi! Les données sont dynamiques.');
    
  } catch (error) {
    console.error('❌ Erreur:', error.message);
    console.error('Détails:', error);
  }
}

// Exécuter seulement si appelé directement
if (typeof window === 'undefined') {
  debugTransmissions();
}