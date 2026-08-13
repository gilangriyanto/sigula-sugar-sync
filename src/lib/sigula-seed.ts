import { addDays, monthKey, toISO } from "./format";
import {
  GRADES,
  type Biaya,
  type Eksportir,
  type Grade,
  type Karyawan,
  type Pembelian,
  type Penjualan,
  type Petani,
  type RiwayatHarga,
  type SesiTungku,
  type SigulaState,
  type StokMove,
} from "./sigula-types";

/** deterministic pseudo random */
function rng(seed: number) {
  let s = seed;
  return () => {
    s = (s * 1664525 + 1013904223) % 4294967296;
    return s / 4294967296;
  };
}

const NAMA_PETANI = [
  "Sukirman",
  "Haji Wardi",
  "Rohmat Hidayat",
  "Kastam",
  "Bu Yatmi",
  "Darsono",
  "Slamet Riyadi",
  "Tarjo",
  "Nur Kholis",
  "Mbah Sanip",
  "Warsito",
  "Umi Kulsum",
  "Basuki",
  "Rasman",
  "Karyadi",
];

const NAMA_KARYAWAN = [
  "Asep Saepudin",
  "Pardi",
  "Joko Susilo",
  "Bambang Setiawan",
  "Eko Prasetyo",
  "Rudi Hartono",
  "Agus Salim",
  "Wahyu Nugroho",
  "Sugeng Riyadi",
  "Iwan Setiawan",
  "Dedi Kurniawan",
  "Sarif Hidayat",
  "Tono Martono",
  "Yanto",
  "Maman Suryana",
  "Rahmat Fauzi",
  "Hendra Gunawan",
  "Ujang Solihin",
  "Cecep Firmansyah",
  "Andri Wibowo",
  "Nanang Sutrisno",
  "Gunawan",
  "Trisno Widodo",
  "Fajar Ramadhan",
  "Imam Syafi'i",
  "Kurnia Sandi",
  "Bayu Anggara",
  "Rizal Efendi",
];

const EKSPORTIR: Eksportir[] = [
  { id: "e1", nama: "PT Global Sweet Export", kontak: "021-5567890" },
  { id: "e2", nama: "CV Nusantara Sugar Trade", kontak: "0812-3344-5566" },
  { id: "e3", nama: "PT Anugerah Manis Internasional", kontak: "031-8891234" },
  { id: "e4", nama: "PT Java Palm Commodity", kontak: "0857-1122-3344" },
];

export const HARGA_AWAL: Record<Grade, number> = {
  "NS 1": 14500,
  "NS 2": 12750,
  Kecap: 9500,
};

const HARGA_JUAL_KRISTAL = 24500;
const HARGA_JUAL_BRONDOL = 16500;

export function buildSeed(todayISO: string): SigulaState {
  const rand = rng(20260813);
  const pick = <T>(arr: T[]) => arr[Math.floor(rand() * arr.length)]!;
  const int = (min: number, max: number) => Math.floor(min + rand() * (max - min + 1));

  const petani: Petani[] = NAMA_PETANI.map((nama, i) => {
    const member = i % 3 !== 2;
    return {
      id: `p${i + 1}`,
      nama,
      status: member ? "Member" : "Non-Member",
      nomorMember: member ? String(200 + i * 3 + 11) : "",
      kontak: `08${int(11, 89)}-${int(1000, 9999)}-${int(1000, 9999)}`,
      alamat: pick([
        "Desa Sukamaju, Kec. Cikoneng",
        "Desa Cibungur, Kec. Pagaden",
        "Desa Karanganyar, Kec. Wanareja",
        "Desa Mekarsari, Kec. Cimalaka",
        "Desa Tegalsari, Kec. Bumiayu",
      ]),
    };
  });

  const karyawan: Karyawan[] = NAMA_KARYAWAN.map((nama, i) => ({
    id: `k${i + 1}`,
    nama,
    kontak: `08${int(11, 89)}-${int(1000, 9999)}-${int(1000, 9999)}`,
  }));

  const pembelian: Pembelian[] = [];
  const stokMoves: StokMove[] = [];
  const sesi: SesiTungku[] = [];
  const penjualan: Penjualan[] = [];
  const biaya: Biaya[] = [];

  // opening balance 6 bulan lalu
  const startISO = addDays(todayISO, -175);
  stokMoves.push(
    {
      id: "sm-open-1",
      tanggal: startISO,
      jenis: "Masuk",
      kategori: "NS 1",
      jumlah: 3200,
      keterangan: "Saldo awal sistem",
    },
    {
      id: "sm-open-2",
      tanggal: startISO,
      jenis: "Masuk",
      kategori: "NS 2",
      jumlah: 2400,
      keterangan: "Saldo awal sistem",
    },
    {
      id: "sm-open-3",
      tanggal: startISO,
      jenis: "Masuk",
      kategori: "Kecap",
      jumlah: 1500,
      keterangan: "Saldo awal sistem",
    },
    {
      id: "sm-open-4",
      tanggal: startISO,
      jenis: "Masuk",
      kategori: "Kristal",
      jumlah: 1800,
      keterangan: "Saldo awal sistem",
    },
    {
      id: "sm-open-5",
      tanggal: startISO,
      jenis: "Masuk",
      kategori: "Brondol",
      jumlah: 520,
      keterangan: "Saldo awal sistem",
    },
  );

  let seq = 0;
  const id = (pre: string) => `${pre}-${++seq}`;

  // riwayat harga (perubahan tiap ~2 bulan)
  const riwayatHarga: RiwayatHarga[] = [];
  GRADES.forEach((g, gi) => {
    let harga = HARGA_AWAL[g] - 1200;
    for (let step = 0; step < 2; step++) {
      const baru = harga + 600;
      riwayatHarga.push({
        id: id("rh"),
        grade: g,
        tanggal: addDays(todayISO, -150 + step * 60 + gi * 4),
        hargaLama: harga,
        hargaBaru: baru,
      });
      harga = baru;
    }
  });

  // 175 hari histori
  for (let d = 175; d >= 0; d--) {
    const tanggal = addDays(todayISO, -d);
    const dow = new Date(tanggal + "T00:00:00").getDay();
    if (dow === 0) continue; // minggu libur

    // pembelian bahan: 2-4 transaksi / hari
    const nBeli = int(2, 4);
    for (let i = 0; i < nBeli; i++) {
      const pet = pick(petani);
      const grade = pick(GRADES);
      const kgBeli = int(120, 620);
      const harga = HARGA_AWAL[grade] - (d > 90 ? 600 : 0);
      const trx: Pembelian = {
        id: id("beli"),
        tanggal,
        petaniId: pet.id,
        grade,
        kg: kgBeli,
        harga,
        total: kgBeli * harga,
      };
      pembelian.push(trx);
      stokMoves.push({
        id: id("sm"),
        tanggal,
        jenis: "Masuk",
        kategori: grade,
        jumlah: kgBeli,
        keterangan: `Pembelian dari ${pet.nama}`,
      });
    }

    // sesi tungku: 8-15 tungku paralel per hari
    const nSesi = d === 0 ? 12 : int(8, 15);
    const shuffled = [...karyawan].sort(() => rand() - 0.5);
    for (let t = 0; t < nSesi; t++) {
      const grade = pick(GRADES);
      const kgBahan = int(80, 160);
      const k1 = shuffled[(t * 2) % karyawan.length]!;
      let k2 = shuffled[(t * 2 + 1) % karyawan.length]!;
      if (k2.id === k1.id) k2 = shuffled[(t * 2 + 2) % karyawan.length]!;
      const rendemen = 0.9 + rand() * 0.06;
      const totalHasil = Math.round(kgBahan * rendemen);
      const kgBrondol = Math.round(totalHasil * (0.12 + rand() * 0.1));
      const kgKristal = totalHasil - kgBrondol;
      const belumSelesai = d === 0 && t >= 8;
      sesi.push({
        id: id("sesi"),
        tanggal,
        kodeTungku: `TGK-${String(t + 1).padStart(2, "0")}`,
        grade,
        kgBahan,
        karyawanIds: [k1.id, k2.id],
        kgKristal: belumSelesai ? null : kgKristal,
        kgBrondol: belumSelesai ? null : kgBrondol,
        status: belumSelesai ? "Sedang Diproses" : "Selesai",
      });
      if (!belumSelesai) {
        stokMoves.push(
          {
            id: id("sm"),
            tanggal,
            jenis: "Keluar",
            kategori: grade,
            jumlah: kgBahan,
            keterangan: `Produksi tungku TGK-${String(t + 1).padStart(2, "0")}`,
          },
          {
            id: id("sm"),
            tanggal,
            jenis: "Masuk",
            kategori: "Kristal",
            jumlah: kgKristal,
            keterangan: `Hasil tungku TGK-${String(t + 1).padStart(2, "0")}`,
          },
          {
            id: id("sm"),
            tanggal,
            jenis: "Masuk",
            kategori: "Brondol",
            jumlah: kgBrondol,
            keterangan: `Hasil tungku TGK-${String(t + 1).padStart(2, "0")}`,
          },
        );
      }
    }

    // penjualan tiap 5 hari
    if (d % 5 === 2) {
      const eks = pick(EKSPORTIR);
      const kgK = int(1800, 4200);
      const kgB = int(300, 900);
      const hargaK = HARGA_JUAL_KRISTAL - (d > 90 ? 900 : 0);
      const hargaB = HARGA_JUAL_BRONDOL - (d > 90 ? 700 : 0);
      penjualan.push({
        id: id("jual"),
        noInvoice: `INV/${tanggal.slice(0, 4)}/${String(penjualan.length + 1).padStart(4, "0")}`,
        tanggal,
        eksportirId: eks.id,
        kristal: { kg: kgK, harga: hargaK },
        brondol: { kg: kgB, harga: hargaB },
        total: kgK * hargaK + kgB * hargaB,
      });
      stokMoves.push(
        {
          id: id("sm"),
          tanggal,
          jenis: "Keluar",
          kategori: "Kristal",
          jumlah: kgK,
          keterangan: `Penjualan ke ${eks.nama}`,
        },
        {
          id: id("sm"),
          tanggal,
          jenis: "Keluar",
          kategori: "Brondol",
          jumlah: kgB,
          keterangan: `Penjualan ke ${eks.nama}`,
        },
      );
    }
  }

  // biaya operasional: beberapa per bulan
  const kategoriBiaya = ["Listrik", "Transport", "Sewa", "Lainnya"] as const;
  for (let d = 170; d >= 0; d -= 9) {
    const tanggal = addDays(todayISO, -d);
    const kat = pick([...kategoriBiaya]);
    biaya.push({
      id: id("biaya"),
      tanggal,
      keterangan:
        kat === "Listrik"
          ? "Tagihan listrik pabrik"
          : kat === "Transport"
            ? "BBM & transport pengiriman"
            : kat === "Sewa"
              ? "Sewa gudang penyimpanan"
              : "Perawatan tungku & peralatan",
      kategori: kat,
      jumlah: int(15, 90) * 100000,
    });
  }

  return {
    petani,
    karyawan,
    eksportir: EKSPORTIR,
    hargaBeli: { ...HARGA_AWAL },
    riwayatHarga,
    tarif: { kristal: 1150, brondol: 800, uangMakan: 5000 },
    pembelian,
    stokMoves,
    sesi,
    penjualan,
    biaya,
    gajiDibayar: [],
  };
}

export function todayISO(): string {
  return toISO(new Date());
}

export const currentMonthKey = () => monthKey(todayISO());
