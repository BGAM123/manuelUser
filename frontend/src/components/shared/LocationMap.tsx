/**
 * Composant carte (Leaflet + OpenStreetMap, gratuit, sans clé API) pour la
 * section Localisation des biens. Deux exports :
 *   - LocationMap        : affiche des pins pour une liste de localisations
 *                          (historique), la plus récente mise en avant.
 *   - LocationPickerMap  : carte cliquable pour choisir un point (latitude/
 *                          longitude) — utilisée dans le formulaire de saisie.
 *
 * Chargé en import dynamique (React.lazy) là où il est utilisé, pour ne pas
 * alourdir le paquet JS principal avec Leaflet pour les utilisateurs qui
 * n'ouvrent jamais la section Localisation.
 */

import { useEffect, type ReactNode } from "react";
import { MapContainer, TileLayer, Marker, Tooltip, useMap, useMapEvents } from "react-leaflet";
import L from "leaflet";
import "leaflet/dist/leaflet.css";
import markerIcon2x from "leaflet/dist/images/marker-icon-2x.png";
import markerIcon from "leaflet/dist/images/marker-icon.png";
import markerShadow from "leaflet/dist/images/marker-shadow.png";

// Les icônes par défaut de Leaflet référencent des chemins relatifs qui ne
// résolvent pas correctement une fois passés par un bundler (Vite/Webpack) —
// correctif standard : les réassigner explicitement vers les imports.
delete (L.Icon.Default.prototype as unknown as { _getIconUrl?: unknown })._getIconUrl;
L.Icon.Default.mergeOptions({
  iconRetinaUrl: markerIcon2x,
  iconUrl: markerIcon,
  shadowUrl: markerShadow,
});

const CAMEROON_CENTER: [number, number] = [7.3697, 12.3547];
const CAMEROON_ZOOM = 6;
const TILE_URL = "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png";
const TILE_ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

// ─── Affichage d'un historique de localisations ─────────────────────────────

export interface MapLocationPoint {
  id: number;
  latitude: number;
  longitude: number;
  isCurrent?: boolean;
  /**
   * Contenu affiché au survol du pin (infobulle). Volontairement un ReactNode
   * libre plutôt que des champs figés (nom, référence...) — LocationMap reste
   * générique, sans dépendance au domaine "bien" ; c'est l'appelant qui sait
   * quelles informations sont pertinentes à afficher (même principe que la
   * prop `render` des colonnes de DataTable).
   */
  tooltip?: ReactNode;
}

// Recentre/ajuste le zoom pour englober TOUS les points — sans ça, la carte
// reste centrée sur le premier point à un zoom fixe (15, niveau rue) et les
// autres biens, même existants sur la carte, sortent du cadre visible.
function FitAllBounds({ locations }: { locations: MapLocationPoint[] }) {
  const map = useMap();
  const key = locations.map((l) => `${l.id}:${l.latitude},${l.longitude}`).join("|");
  useEffect(() => {
    if (locations.length < 2) return;
    const bounds = L.latLngBounds(locations.map((l) => [l.latitude, l.longitude] as [number, number]));
    map.fitBounds(bounds, { padding: [40, 40] });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key]);
  return null;
}

export function LocationMap({ locations, height = 300 }: { locations: MapLocationPoint[]; height?: number }) {
  const center: [number, number] = locations.length > 0
    ? [locations[0].latitude, locations[0].longitude]
    : CAMEROON_CENTER;
  const zoom = locations.length > 0 ? 15 : CAMEROON_ZOOM;

  return (
    <div style={{ height }} className="overflow-hidden rounded-lg border border-border">
      <MapContainer center={center} zoom={zoom} style={{ height: "100%", width: "100%" }}>
        <TileLayer attribution={TILE_ATTRIBUTION} url={TILE_URL} />
        <FitAllBounds locations={locations} />
        {locations.map((loc) => (
          <Marker
            key={loc.id}
            position={[loc.latitude, loc.longitude]}
            opacity={loc.isCurrent ? 1 : 0.55}
          >
            {loc.tooltip && (
              // direction="auto" — Leaflet choisit le côté (haut/bas/gauche/droite)
              // selon la place disponible dans la carte, pour ne jamais couper
              // l'infobulle quand le marqueur est proche d'un bord.
              <Tooltip direction="auto" offset={[0, -28]} opacity={1}>
                {loc.tooltip}
              </Tooltip>
            )}
          </Marker>
        ))}
      </MapContainer>
    </div>
  );
}

// ─── Sélecteur de point par clic ────────────────────────────────────────────

function RecenterOnValue({ latitude, longitude }: { latitude: number; longitude: number }) {
  const map = useMap();
  useEffect(() => {
    map.setView([latitude, longitude], map.getZoom());
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [latitude, longitude]);
  return null;
}

function ClickHandler({ onPick }: { onPick: (lat: number, lng: number) => void }) {
  useMapEvents({
    click(e) {
      onPick(e.latlng.lat, e.latlng.lng);
    },
  });
  return null;
}

export function LocationPickerMap({ value, onChange, height = 300, readOnly = false }: {
  value: { latitude: number; longitude: number } | null;
  onChange: (v: { latitude: number; longitude: number }) => void;
  height?: number;
  /** Désactive le clic pour placer un point — pour afficher un point déjà
   * connu (ex: extrait d'un fichier importé) sans permettre de le déplacer. */
  readOnly?: boolean;
}) {
  const center: [number, number] = value ? [value.latitude, value.longitude] : CAMEROON_CENTER;
  const zoom = value ? 15 : CAMEROON_ZOOM;

  return (
    <div style={{ height }} className="overflow-hidden rounded-lg border border-border">
      <MapContainer center={center} zoom={zoom} style={{ height: "100%", width: "100%" }}>
        <TileLayer attribution={TILE_ATTRIBUTION} url={TILE_URL} />
        {!readOnly && <ClickHandler onPick={(lat, lng) => onChange({ latitude: lat, longitude: lng })} />}
        {value && (
          <>
            <Marker position={[value.latitude, value.longitude]} />
            <RecenterOnValue latitude={value.latitude} longitude={value.longitude} />
          </>
        )}
      </MapContainer>
    </div>
  );
}
