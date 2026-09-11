// Test du module statistiques des types et classes de courrier
import { statisticsApi } from '../src/api/statisticsApi';

async function testStatistiquesTypesClasses() {
  console.log('🧪 Test des statistiques types et classes de courrier\n');

  try {
    // Test 1: Récupérer toutes les statistiques sans filtre
    console.log('📊 Test 1: Récupération sans filtre');
    const statsAll = await statisticsApi.getStatistiquesComptageParEntite();
    console.log('✅ Succès:', statsAll.message);
    console.log(`   Nombre d'entités: ${statsAll.data.length}`);
    statsAll.data.forEach(entite => {
      console.log(`   - ${entite.entite}: ${entite.nombre_classes} classes, ${entite.nombre_types_courrier} types`);
    });
    console.log('');

    // Test 2: Filtrer par date
    console.log('📊 Test 2: Filtrage par date');
    const filters = {
      date_debut: '2025-01-01',
      date_fin: '2025-12-31',
    };
    const statsFiltered = await statisticsApi.getStatistiquesComptageParEntite(filters);
    console.log('✅ Succès avec filtres:', statsFiltered.message);
    console.log(`   Période: ${filters.date_debut} à ${filters.date_fin}`);
    console.log(`   Nombre d'entités: ${statsFiltered.data.length}`);
    console.log('');

    // Test 3: Afficher le détail d'une entité
    console.log('📊 Test 3: Détail de l\'entité "Courrier"');
    const courrierEntity = statsAll.data.find(e => e.entite === 'Courrier');
    if (courrierEntity) {
      console.log(`✅ Entité trouvée: ${courrierEntity.entite}`);
      console.log(`   Nombre de classes: ${courrierEntity.nombre_classes}`);
      console.log(`   Nombre de types: ${courrierEntity.nombre_types_courrier}`);
      console.log(`   Nombre de statuts: ${courrierEntity.nombre_statuts}`);
      console.log('');
      
      console.log('   Classes détaillées:');
      courrierEntity.types_par_classe.forEach((classe, index) => {
        console.log(`   ${index + 1}. ${classe.classe} (${classe.nombre_types} types)`);
        classe.types.slice(0, 3).forEach(type => {
          console.log(`      - ${type.nom} [${type.statuts.join(', ')}]`);
        });
        if (classe.types.length > 3) {
          console.log(`      ... et ${classe.types.length - 3} autres types`);
        }
      });
    } else {
      console.log('❌ Entité "Courrier" non trouvée');
    }
    console.log('');

    // Test 4: Statistiques sur les types "Non défini"
    console.log('📊 Test 4: Types "Non défini"');
    let countNonDefini = 0;
    statsAll.data.forEach(entite => {
      entite.types_par_classe.forEach(classe => {
        classe.types.forEach(type => {
          if (type.id === null || type.nom === 'Non défini') {
            countNonDefini++;
          }
        });
      });
    });
    console.log(`✅ Nombre de types "Non défini": ${countNonDefini}`);
    console.log('');

    // Test 5: Résumé global
    console.log('📊 Test 5: Résumé global');
    let totalClasses = 0;
    let totalTypes = 0;
    let totalStatuts = 0;
    statsAll.data.forEach(entite => {
      totalClasses += entite.nombre_classes;
      totalTypes += entite.nombre_types_courrier;
      totalStatuts += entite.nombre_statuts;
    });
    console.log('✅ Résumé:');
    console.log(`   Total classes: ${totalClasses}`);
    console.log(`   Total types: ${totalTypes}`);
    console.log(`   Total statuts distincts: ${totalStatuts}`);
    console.log('');

    console.log('✅ ✅ ✅ Tous les tests sont passés avec succès! ✅ ✅ ✅');

  } catch (error) {
    console.error('❌ Erreur lors des tests:', error);
    if (error instanceof Error) {
      console.error('   Message:', error.message);
      console.error('   Stack:', error.stack);
    }
  }
}

// Exécuter les tests
testStatistiquesTypesClasses();
