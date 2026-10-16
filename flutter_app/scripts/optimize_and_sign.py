"""
SchoPlus Daleel Mobile App - Production APK Stripper, Zipalign & Signer
Removes unneeded debug symbols from native ELF .so files, page-aligns with zipalign,
and signs with production keystore using apksigner (V2/V3).
"""
import os
import sys
import struct
import zipfile
import subprocess
import shutil

def strip_elf(data: bytes) -> bytes:
    if data[:4] != b'\x7fELF':
        return data
    is_64 = (data[4] == 2)
    little_endian = (data[5] == 1)
    endian_char = '<' if little_endian else '>'
    
    if is_64:
        e_phoff = struct.unpack_from(endian_char + 'Q', data, 32)[0]
        e_phentsize = struct.unpack_from(endian_char + 'H', data, 54)[0]
        e_phnum = struct.unpack_from(endian_char + 'H', data, 56)[0]
        max_extent = 0
        for i in range(e_phnum):
            ph_offset = e_phoff + i * e_phentsize
            p_type, p_flags, p_offset, p_vaddr, p_paddr, p_filesz = struct.unpack_from(endian_char + 'IIQQQQ', data, ph_offset)
            if p_type == 1: # PT_LOAD
                extent = p_offset + p_filesz
                if extent > max_extent:
                    max_extent = extent
        stripped = bytearray(data[:max_extent])
        struct.pack_into(endian_char + 'Q', stripped, 40, 0)
        struct.pack_into(endian_char + 'H', stripped, 60, 0)
        struct.pack_into(endian_char + 'H', stripped, 62, 0)
        return bytes(stripped)
    else:
        e_phoff = struct.unpack_from(endian_char + 'I', data, 28)[0]
        e_phentsize = struct.unpack_from(endian_char + 'H', data, 42)[0]
        e_phnum = struct.unpack_from(endian_char + 'H', data, 44)[0]
        max_extent = 0
        for i in range(e_phnum):
            ph_offset = e_phoff + i * e_phentsize
            p_type, p_offset, p_vaddr, p_paddr, p_filesz = struct.unpack_from(endian_char + 'IIIII', data, ph_offset)
            if p_type == 1: # PT_LOAD
                extent = p_offset + p_filesz
                if extent > max_extent:
                    max_extent = extent
        stripped = bytearray(data[:max_extent])
        struct.pack_into(endian_char + 'I', stripped, 32, 0)
        struct.pack_into(endian_char + 'H', stripped, 48, 0)
        struct.pack_into(endian_char + 'H', stripped, 50, 0)
        return bytes(stripped)

def main():
    base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    src_apk = os.path.join(base_dir, 'build', 'app', 'outputs', 'flutter-apk', 'app-release.apk')
    unaligned_apk = os.path.join(base_dir, 'build', 'app', 'outputs', 'flutter-apk', 'app-unaligned.apk')
    aligned_apk = os.path.join(base_dir, 'build', 'app', 'outputs', 'flutter-apk', 'app-optimized.apk')
    dest_apk = os.path.join(os.path.dirname(base_dir), 'downloads', 'daleel-schoplus.apk')
    
    if not os.path.exists(src_apk):
        print(f"Error: {src_apk} not found. Run 'flutter build apk --release' first.")
        sys.exit(1)
        
    print(f"Reading {src_apk} ({os.path.getsize(src_apk)/(1024*1024):.2f} MB)...")
    with zipfile.ZipFile(src_apk, 'r') as zin, zipfile.ZipFile(unaligned_apk, 'w', zipfile.ZIP_DEFLATED) as zout:
        for item in zin.infolist():
            if item.filename.startswith('META-INF/') and (item.filename.endswith('.SF') or item.filename.endswith('.RSA') or item.filename.endswith('.MF')):
                continue
            data = zin.read(item.filename)
            if item.filename.endswith('.so'):
                orig_len = len(data)
                data = strip_elf(data)
                print(f"  Stripped {item.filename}: {orig_len/(1024*1024):.1f}MB -> {len(data)/(1024*1024):.1f}MB")
                zout.writestr(item.filename, data, compress_type=zipfile.ZIP_STORED)
            else:
                zout.writestr(item, data)
                
    zipalign = r'C:\Users\MohamedYoseif\AppData\Local\Android\Sdk\build-tools\34.0.0\zipalign.exe'
    if os.path.exists(aligned_apk):
        os.remove(aligned_apk)
    print("Running zipalign (16KB / 4-byte boundary)...")
    subprocess.check_call([zipalign, '-v', '-p', '4', unaligned_apk, aligned_apk], stdout=subprocess.DEVNULL)
    
    apksigner = r'C:\Users\MohamedYoseif\AppData\Local\Android\Sdk\build-tools\34.0.0\apksigner.bat'
    keystore = os.path.join(base_dir, 'android', 'daleel-release-key.jks')
    print("Signing with production keystore...")
    cmd = [
        apksigner, 'sign',
        '--v1-signing-enabled', 'true',
        '--v2-signing-enabled', 'true',
        '--v3-signing-enabled', 'true',
        '--ks', keystore,
        '--ks-key-alias', 'schoplus',
        '--ks-pass', 'pass:schoplus2026',
        '--key-pass', 'pass:schoplus2026',
        aligned_apk
    ]
    subprocess.check_call(cmd)
    
    # Verify
    verify_cmd = [apksigner, 'verify', '--verbose', aligned_apk]
    res = subprocess.check_output(verify_cmd).decode('utf-8')
    if 'Verifies' not in res:
        print("Signature verification failed:", res)
        sys.exit(1)
    print("APK successfully verified!")
    
    # Copy to downloads
    os.makedirs(os.path.dirname(dest_apk), exist_ok=True)
    shutil.copy2(aligned_apk, dest_apk)
    print(f"Production Release APK copied to: {dest_apk} ({os.path.getsize(dest_apk)/(1024*1024):.2f} MB)")
    
    # Clean up intermediate unaligned
    if os.path.exists(unaligned_apk):
        os.remove(unaligned_apk)

if __name__ == '__main__':
    main()
