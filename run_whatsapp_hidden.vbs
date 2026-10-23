Set WshShell = CreateObject("WScript.Shell")
Set FSO = CreateObject("Scripting.FileSystemObject")
batPath = FSO.BuildPath(FSO.GetParentFolderName(WScript.ScriptFullName), "start_whatsapp.bat")
WshShell.Run chr(34) & batPath & Chr(34), 0
Set WshShell = Nothing
Set FSO = Nothing
