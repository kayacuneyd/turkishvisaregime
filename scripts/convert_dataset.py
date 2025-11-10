#!/usr/bin/env python3
"""
Utility to convert the legacy `turkiye_vize_rejimi.json` dataset into the
structured schema consumed by `data/data.json`.

Requires:
  pip install deep-translator pycountry unidecode
"""

from __future__ import annotations

import json
import re
from datetime import date
from pathlib import Path
from typing import Dict, Tuple

import pycountry
from deep_translator import GoogleTranslator
from unidecode import unidecode

BASE_DIR = Path(__file__).resolve().parent.parent
LEGACY_FILE = BASE_DIR / "turkiye_vize_rejimi.json"
TARGET_FILE = BASE_DIR / "data" / "data.json"

SOURCE_DEFAULT = "https://www.mfa.gov.tr/visa-information-for-foreigners.en.mfa"
SOURCE_E_VISA = "https://www.evisa.gov.tr/"
TODAY = date.today().isoformat()

COUNTRY_OVERRIDES: Dict[str, Tuple[str, str]] = {
    "abd": ("us", "United States of America"),
    "andora": ("ad", "Andorra"),
    "antigua barboda": ("ag", "Antigua and Barbuda"),
    "bagimsiz samoa": ("ws", "Samoa"),
    "brezilya": ("br", "Brazil"),
    "brunei": ("bn", "Brunei Darussalam"),
    "dogu timor": ("tl", "Timor-Leste"),
    "ekvator": ("ec", "Ecuador"),
    "etiyopya": ("et", "Ethiopia"),
    "fas": ("ma", "Morocco"),
    "fildisi sahili": ("ci", "Côte d'Ivoire"),
    "gambiya": ("gm", "Gambia"),
    "gana": ("gh", "Ghana"),
    "gine": ("gn", "Guinea"),
    "gine bissau": ("gw", "Guinea-Bissau"),
    "hirvatistan": ("hr", "Croatia"),
    "hollanda": ("nl", "Netherlands"),
    "hongkong": ("hk", "Hong Kong SAR"),
    "irak": ("iq", "Iraq"),
    "ingiltere": ("gb", "United Kingdom"),
    "israil": ("il", "Israel"),
    "isvec": ("se", "Sweden"),
    "isvicre": ("ch", "Switzerland"),
    "jamaika": ("jm", "Jamaica"),
    "kanada": ("ca", "Canada"),
    "katar": ("qa", "Qatar"),
    "kibris rum kesimi gkry": ("cy", "Republic of Cyprus"),
    "kktc": ("nc", "Turkish Republic of Northern Cyprus"),
    "kolombiya": ("co", "Colombia"),
    "komorlar birligi": ("km", "Comoros"),
    "kongo demokratik cumhuriyeti": ("cd", "Democratic Republic of the Congo"),
    "kore cumhuriyeti guney": ("kr", "Republic of Korea"),
    "kore demokratik halk cumhuriyeti kuzey": ("kp", "Democratic People's Republic of Korea"),
    "kosova": ("xk", "Kosovo"),
    "macaristan": ("hu", "Hungary"),
    "makau oib": ("mo", "Macao SAR"),
    "mikronezya": ("fm", "Federated States of Micronesia"),
    "moritanya": ("mr", "Mauritania"),
    "mozambik": ("mz", "Mozambique"),
    "nikaragua": ("ni", "Nicaragua"),
    "orta afrika cumhuriyeti": ("cf", "Central African Republic"),
    "palau cumhuriyeti": ("pw", "Palau"),
    "saint lucia": ("lc", "Saint Lucia"),
    "siera leone": ("sl", "Sierra Leone"),
    "somali": ("so", "Somalia"),
    "sili": ("cl", "Chile"),
    "trinidad tobago": ("tt", "Trinidad and Tobago"),
    "tunus": ("tn", "Tunisia"),
    "vatikan": ("va", "Vatican City"),
    "yunanistan": ("gr", "Greece"),
}

FLAG_OVERRIDES = {
    "nc": "https://upload.wikimedia.org/wikipedia/commons/e/ef/Flag_of_the_Turkish_Republic_of_Northern_Cyprus.svg"
}

translator = GoogleTranslator(source="auto", target="en")
translation_cache: Dict[str, str] = {}


def normalize(name: str) -> str:
    return re.sub(r"[^a-z0-9]+", " ", unidecode(name).lower()).strip()


def translate(text: str) -> str:
    cleaned = (text or "").strip()
    if not cleaned:
        return ""
    if cleaned in translation_cache:
        return translation_cache[cleaned]
    try:
        translation_cache[cleaned] = translator.translate(cleaned)
    except Exception:
        translation_cache[cleaned] = cleaned
    return translation_cache[cleaned]


def lookup_country(raw_name: str) -> Tuple[str, str]:
    norm = normalize(raw_name)
    if norm in COUNTRY_OVERRIDES:
        iso2, english = COUNTRY_OVERRIDES[norm]
        return iso2.lower(), english

    translated = translate(raw_name) or raw_name
    candidates = [
        translated,
        translated.replace("Republic of", "").strip(),
        translated.replace("Republic", "").strip(),
        translated.replace("The ", "").strip(),
        translated.split("(")[0].strip(),
        raw_name,
        unidecode(raw_name),
    ]
    for candidate in candidates:
        candidate = candidate.strip()
        if not candidate:
            continue
        try:
            country = pycountry.countries.lookup(candidate)
            return country.alpha_2.lower(), country.name
        except LookupError:
            continue
    raise ValueError(f"Unable to determine ISO code for {raw_name!r} (translated: {translated!r})")


def contains_evisa_phrase(text: str) -> bool:
    lowered = text.lower()
    if any(token in lowered for token in ("www.evisa.gov.tr", "e-vize", "e-visa", "evisa")):
        return True
    return " e vize" in lowered or lowered.startswith("e vize")


def determine_visa_type(entry: dict) -> str:
    ordinary = (entry.get("ordinary_umuma") or "").lower()
    blob = " ".join(
        filter(
            None,
            [
                entry.get("ordinary_umuma"),
                entry.get("official_resmi"),
                entry.get("service_hizmet"),
                entry.get("diplomatic_diplomatik"),
                entry.get("notes"),
                entry.get("duration_mentions"),
            ],
        )
    ).lower()
    evisa_field = (entry.get("evisa") or "").lower()
    evisa_hint = (
        contains_evisa_phrase(ordinary)
        or contains_evisa_phrase(blob)
        or "yes" in evisa_field
    )

    if "muaf" in ordinary:
        return "no_visa"
    if evisa_hint:
        return "e_visa"
    if any(term in ordinary for term in ("vizeye tabi", "vizeye tabidir", "vizeye tabii", "vize uygulanir", "vizeye tabidirler")):
        return "consular"
    if "muaf" in blob:
        return "no_visa"
    return "consular"


def pick_description(entry: dict) -> str:
    for key in (
        "ordinary_umuma",
        "notes",
        "official_resmi",
        "service_hizmet",
        "diplomatic_diplomatik",
    ):
        value = (entry.get(key) or "").strip()
        if value:
            return value
    return "Bu ülke için kaynak açıklaması bulunamadı."


def pick_duration(entry: dict) -> str:
    duration = (entry.get("duration_mentions") or "").strip()
    if duration:
        return duration
    return "Kalış süresi kaynak tarafından belirtilmemiştir."


def build_record(entry: dict) -> dict:
    iso2, country_en = lookup_country(entry["country"])
    description_tr = pick_description(entry)
    duration_tr = pick_duration(entry)
    visa_type = determine_visa_type(entry)

    return {
        "country": country_en,
        "iso2": iso2,
        "visa_type": visa_type,
        "descriptions": {
            "tr": description_tr,
            "en": translate(description_tr) or description_tr,
        },
        "durations": {
            "tr": duration_tr,
            "en": translate(duration_tr) or duration_tr,
        },
        "flag": FLAG_OVERRIDES.get(iso2, f"https://flagcdn.com/{iso2}.svg"),
        "source": SOURCE_E_VISA if visa_type == "e_visa" else SOURCE_DEFAULT,
        "last_update": TODAY,
    }


def main() -> None:
    legacy_data = json.loads(LEGACY_FILE.read_text(encoding="utf-8"))
    new_records = [build_record(entry) for entry in legacy_data]
    new_records.sort(key=lambda item: item["country"])
    TARGET_FILE.write_text(json.dumps(new_records, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"Converted {len(new_records)} records → {TARGET_FILE}")


if __name__ == "__main__":
    main()
