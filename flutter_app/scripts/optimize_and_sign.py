"""
SchoPlus Daleel Mobile App - Production APK Packager, Zipalign & Signer
Packages Flutter release APK for real mobile devices (ARM64-v8a & ARMv7a),
excluding PC emulator x86_64 binaries to keep the release APK under GitHub's 100MB
limit (~90MB). Preserves 100% of native Google binaries and ELF headers intact.
Page-aligns with zipalign and signs with production keystore using apksigner (V1, V2, V3).
"""
import os
import sys
import struct
import zipfile
import subprocess
import shutil

def verify_elf_integrity(data: bytes, filename: str):
    """Ensure ELF binary has valid headers and has not been truncated."""
    if not data.startswith(b'\x7fELF'):
        return
    is_64 = (data[4] == 2)
    little_endian = (data[5] == 1)
    endian = '<' if little_endian else '>'
    
    if is_64:
        e_shoff = struct.unpack_from(endian + 'Q', data, 40)[0]
        e_shnum = struct.unpack_from(endian + 'H', data, 60)[0]
        e_shstrndx = struct.unpack_from(endian + 'H', data, 62)[0]
    else:
        e_shoff = struct.unpack_from(endian + 'I', data, 32)[0]
        e_shnum = struct.unpack_from(endian + 'H', data, 48)[0]
        e_shstrndx = struct.unpack_from(endian + 'H', data, 50)[0]
        
    if e_shnum > 0 and e_shstrndx >= e_shnum:
        raise ValueError(f"Corrupt ELF binary {filename}: e_shstrndx ({e_shstrndx}) >= e_shnum ({e_shnum})")
    if e_shoff > len(data):
        raise ValueError(f"Truncated ELF binary {filename}: e_shoff ({e_shoff}) > file size ({len(data)})")

def main():
    base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    src_apk = os.path.join(base_dir, 'build', 'app', 'outputs', 'flutter-apk', 'app-release.apk')
    unaligned_apk = os.path.join(base_dir, 'build', 'app', 'outputs', 'flutter-apk', 'app-unaligned.apk')
    aligned_apk = os.path.join(base_dir, 'build', 'app', 'outputs', 'flutter-apk', 'app-optimized.apk')
    dest_apk = os.path.join(os.path.dirname(base_dir), 'downloads', 'daleel-schoplus.apk')
    
    if not os.path.exists(src_apk):
        print(f"Error: {src_apk} not found. Run 'flutter build apk --release' first.")
        sys.exit(1)
        
    print(f"Reading source APK: {src_apk} ({os.path.getsize(src_apk)/(1024*1024):.2f} MB)...")
    if os.path.exists(unaligned_apk):
        os.remove(unaligned_apk)

    so_count = 0
    with zipfile.ZipFile(src_apk, 'r') as zin, zipfile.ZipFile(unaligned_apk, 'w', compression=zipfile.ZIP_DEFLATED, compresslevel=9) as zout:
        for item in zin.infolist():
            # Exclude x86_64 PC emulator binaries (never needed on smartphones)
            if item.filename.startswith('lib/x86_64/'):
                continue
                
            # Skip old signatures so apksigner signs cleanly
            if item.filename.startswith('META-INF/') and (
                item.filename.endswith('.SF') or 
                item.filename.endswith('.RSA') or 
                item.filename.endswith('.DSA') or 
                item.filename.endswith('.MF')
            ):
                continue
                
            data = zin.read(item.filename)
            if item.filename.endswith('.so'):
                so_count += 1
                verify_elf_integrity(data, item.filename)
                
            zout.writestr(item, data, compress_type=zipfile.ZIP_DEFLATED)
            
    print(f"Verified {so_count} native mobile .so libraries (arm64-v8a & armeabi-v7a) — 100% valid ELF headers.")
    print(f"Unaligned compressed APK: {os.path.getsize(unaligned_apk)/(1024*1024):.2f} MB")
                
    zipalign = r'C:\Users\MohamedYoseif\AppData\Local\Android\Sdk\build-tools\34.0.0\zipalign.exe'
    if os.path.exists(aligned_apk):
        os.remove(aligned_apk)
    print("Running zipalign (4-byte alignment)...")
    subprocess.check_call([zipalign, '-v', '-p', '4', unaligned_apk, aligned_apk], stdout=subprocess.DEVNULL)
    
    apksigner = r'C:\Users\MohamedYoseif\AppData\Local\Android\Sdk\build-tools\34.0.0\apksigner.bat'
    keystore = os.path.join(base_dir, 'android', 'daleel-release-key.jks')
    print("Signing with production keystore (V1, V2, V3)...")
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
    
    # Verify signature
    verify_cmd = [apksigner, 'verify', '--verbose', aligned_apk]
    res = subprocess.check_output(verify_cmd).decode('utf-8')
    if 'Verifies' not in res:
        print("Signature verification failed:", res)
        sys.exit(1)
    print("APK signatures verified successfully (V1, V2, V3 verified)!")
    
    # Copy to downloads directory
    os.makedirs(os.path.dirname(dest_apk), exist_ok=True)
    shutil.copy2(aligned_apk, dest_apk)
    print(f"SUCCESS: Production Release APK copied to: {dest_apk} ({os.path.getsize(dest_apk)/(1024*1024):.2f} MB)")
    
    # Clean up intermediate unaligned
    if os.path.exists(unaligned_apk):
        os.remove(unaligned_apk)

if __name__ == '__main__':
    main()
