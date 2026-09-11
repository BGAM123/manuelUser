// Script de test pour vérifier la structure des données API
// Collez ce code dans la console de développement du navigateur

async function testApiStructure() {
  console.log("=== TEST STRUCTURE API COURRIERS ===");
  
  const token = localStorage.getItem("token");
  if (!token) {
    console.error("Aucun token trouvé dans localStorage");
    return;
  }

  try {
    const response = await fetch("https://127.0.0.1:8000/core/courrier", {
      headers: {
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/json",
      },
    });
    
    if (!response.ok) {
      console.error("Erreur API:", response.status, response.statusText);
      return;
    }
    
    const data = await response.json();
    console.log("Réponse complète API:", data);
    
    if (data.data && data.data.length > 0) {
      const premier = data.data[0];
      console.log("=== PREMIER COURRIER ===");
      console.log("Objet complet:", premier);
      console.log("Clés disponibles:", Object.keys(premier));
      
      // Vérifier les champs spécifiques
      console.log("=== VÉRIFICATION CHAMPS SPÉCIFIQUES ===");
      console.log("ID:", premier.id);
      console.log("Numero:", premier.numero);
      console.log("Reference:", premier.reference);
      console.log("Objet:", premier.objet);
      console.log("Categorie:", premier.categorie || premier.categorieProvenance);
      console.log("Provenance:", premier.provenance || premier.IdProvenance || premier.expediteur);
      console.log("Structure:", premier.structure || premier.idServiceTraitant || premier.serviceTraitant || premier.destinataire);
      console.log("Date arrivée:", premier.dateArrivee || premier.date_arrivee);
      console.log("Date enregistrement:", premier.createdAt || premier.date_enregistrement);
      console.log("Priorité:", premier.priorite);
      console.log("Statut:", premier.statut);
      
      console.log("=== TOUS LES COURRIERS ===");
      data.data.forEach((courrier, index) => {
        console.log(`Courrier ${index + 1}:`, {
          id: courrier.id,
          numero: courrier.numero,
          reference: courrier.reference,
          structure: courrier.structure || courrier.idServiceTraitant || courrier.serviceTraitant || courrier.destinataire || "VIDE"
        });
      });
    } else {
      console.log("Aucune donnée trouvée");
    }
    
  } catch (error) {
    console.error("Erreur lors du test:", error);
  }
}

// Lancer le test
testApiStructure();
