import { unites, detenteurs } from "./common";

export type Bien = {
  id: string;
  numero: string;
  designation: string;
  categorie: "Immobilier" | "Mobilier" | "Informatique" | "Roulant" | "Cheptel";
  acquisitionDate: string;
  valeurAcquisition: number;
  detenteur: string;
  unite: string;
  etat: "Bon" | "Passable" | "Mauvais";
  statut: "Actif" | "En litige" | "En maintenance" | "À réformer" | "Réformé";
  localisation: string;
};

export const cats: Bien["categorie"][] = [
  "Immobilier",
  "Mobilier",
  "Informatique",
  "Roulant",
  "Cheptel",
];

const etats: Bien["etat"][] = ["Bon", "Passable", "Mauvais"];
const statuts: Bien["statut"][] = [
  "Actif",
  "Actif",
  "Actif",
  "En maintenance",
  "En litige",
  "À réformer",
];

export const designationsBy: Record<Bien["categorie"], string[]> = {
  Immobilier: [
    "Bâtiment administratif",
    "Hangar de stockage",
    "Terrain lot 42",
    "Logement de fonction",
  ],
  Mobilier: ["Bureau ministre", "Chaise visiteur", "Armoire métallique", "Table de réunion"],
  Informatique: [
    "Ordinateur portable Dell",
    "Imprimante HP LaserJet",
    "Serveur Rack",
    "Onduleur 3kVA",
  ],
  Roulant: ["Toyota Hilux", "Peugeot 508", "Moto Yamaha DT", "Camion Isuzu"],
  Cheptel: ["Bovins reproducteurs", "Ovins race locale", "Volailles pondeuses", "Caprins"],
};

function seed(n: number): Bien[] {
  const out: Bien[] = [];
  for (let i = 0; i < n; i++) {
    const categorie = cats[i % cats.length];
    const desigs = designationsBy[categorie];
    out.push({
      id: `B-${String(i + 1).padStart(4, "0")}`,
      numero: `MINEPIA-${2020 + (i % 6)}-${String(i + 1).padStart(4, "0")}`,
      designation: `${desigs[i % desigs.length]} #${i + 1}`,
      categorie,
      acquisitionDate: new Date(2020 + (i % 6), i % 12, ((i * 3) % 27) + 1)
        .toISOString()
        .slice(0, 10),
      valeurAcquisition: Math.round((250000 + i * 87500 + (i % 7) * 130000) / 1000) * 1000,
      detenteur: detenteurs[i % detenteurs.length],
      unite: unites[i % unites.length],
      etat: etats[i % etats.length],
      statut: statuts[i % statuts.length],
      localisation: `Bureau ${100 + (i % 40)}`,
    });
  }
  return out;
}

export const mockBiens: Bien[] = seed(87);