from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from typing import Dict, List, Optional
from datetime import datetime, timedelta
import psutil
import platform
import socket
import os
import logging
from pydantic import BaseModel

# Configure logging
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)

# Initialize FastAPI app
app = FastAPI(
    title="System Monitoring API",
    description="Real-time system monitoring and statistics API",
    version="1.0.0"
)

# Configure CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],  # In production, specify your domains
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Response models
class CPUInfo(BaseModel):
    count: int
    count_logical: int
    percent: float
    percent_per_cpu: List[float]
    freq_current: Optional[float]
    freq_min: Optional[float]
    freq_max: Optional[float]

class MemoryInfo(BaseModel):
    total: int
    available: int
    used: int
    free: int
    percent: float
    total_gb: float
    used_gb: float
    available_gb: float
    free_gb: float

class DiskInfo(BaseModel):
    device: str
    mountpoint: str
    fstype: str
    total: int
    used: int
    free: int
    percent: float
    total_gb: float
    used_gb: float
    free_gb: float

class NetworkInfo(BaseModel):
    bytes_sent: int
    bytes_recv: int
    packets_sent: int
    packets_recv: int
    errin: int
    errout: int
    dropin: int
    dropout: int
    bytes_sent_mb: float
    bytes_recv_mb: float
    bytes_sent_gb: float
    bytes_recv_gb: float

class ProcessInfo(BaseModel):
    pid: int
    name: str
    cpu_percent: float
    memory_percent: float
    memory_mb: float
    status: str
    create_time: str

class SystemInfo(BaseModel):
    hostname: str
    ip_address: str
    platform: str
    platform_release: str
    platform_version: str
    architecture: str
    processor: str
    python_version: str
    boot_time: str
    uptime: str
    current_time: str

# Server Statistics models (for /statistics/servers endpoint)
class ServerCPUInfo(BaseModel):
    percent: float
    count: int
    freq_current: Optional[float]

class ServerMemoryInfo(BaseModel):
    percent: float
    total_gb: float
    used_gb: float

class ServerDiskInfo(BaseModel):
    percent: float
    total_gb: float
    used_gb: float

class ServerNetworkInfo(BaseModel):
    bytes_sent_mb: float
    bytes_recv_mb: float

class ServerProcessInfo(BaseModel):
    name: str
    cpu_percent: float
    memory_mb: float

class WebServerInfo(BaseModel):
    name: str = "WEB Server"
    status: str = "online"
    hostname: str
    ip_address: str
    os_info: str
    platform: str
    uptime: str
    cpu: ServerCPUInfo
    memory: ServerMemoryInfo
    disk: ServerDiskInfo
    network: ServerNetworkInfo
    top_processes: List[ServerProcessInfo]

class AIServerInfo(BaseModel):
    name: str = "AI Server"
    status: str = "online"
    hostname: str
    ip_address: str
    platform: str
    python_version: str
    uptime: str
    cpu: ServerCPUInfo
    memory: ServerMemoryInfo
    disk: ServerDiskInfo
    network: ServerNetworkInfo
    gpu: Optional[Dict] = None
    top_processes: List[ServerProcessInfo]

class VectorStoreInfo(BaseModel):
    name: str = "Vector Store"
    status: str = "online"
    type: str = "ChromaDB"
    unique_documents: int = 0
    total_vectors: int = 0
    collection: str = "documents"

class ServerStatisticsResponse(BaseModel):
    access_level: Optional[str] = None
    message: Optional[str] = None
    ai_server: Optional[AIServerInfo] = None
    web_server: Optional[WebServerInfo] = None
    vector_store: Optional[VectorStoreInfo] = None

# Mock statistics data (replace with actual data source)
MOCK_STATISTICS = {
    "total_documents": 15234,
    "total_sections": 45678,
    "average_sections_per_document": 3.0,
    "documents_by_type": {
        "pdf": 8234,
        "hwp": 4567,
        "docx": 1234,
        "txt": 890,
        "pptx": 309
    },
    "documents_by_sosok": {
        "관리자": 5000,
        "개발팀": 3500,
        "기획팀": 2500,
        "디자인팀": 2234,
        "운영팀": 2000
    },
    "documents_by_site": {
        "본사": 6000,
        "지사1": 3500,
        "지사2": 2734,
        "연구소": 1500,
        "관리자": 1500
    },
    "popular_tags": [
        {"name": "보고서", "count": 3456},
        {"name": "회의록", "count": 2345},
        {"name": "기획안", "count": 1234},
        {"name": "매뉴얼", "count": 890},
        {"name": "가이드", "count": 567}
    ],
    "recent_uploads": []
}

# Utility functions
def get_ip_address():
    """Get the primary IP address of the system"""
    try:
        # Create a socket connection to determine the primary IP
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        s.connect(("8.8.8.8", 80))
        ip_address = s.getsockname()[0]
        s.close()
        return ip_address
    except Exception:
        try:
            # Fallback to hostname resolution
            return socket.gethostbyname(socket.gethostname())
        except:
            return "127.0.0.1"

def format_bytes(bytes_value: int, unit: str = "GB") -> float:
    """Convert bytes to specified unit"""
    units = {
        "KB": 1024,
        "MB": 1024 ** 2,
        "GB": 1024 ** 3,
        "TB": 1024 ** 4
    }
    return round(bytes_value / units.get(unit, 1024 ** 3), 2)

def get_uptime_string() -> str:
    """Get system uptime as a formatted string with days, hours, minutes, and seconds in Korean"""
    boot_time = datetime.fromtimestamp(psutil.boot_time())
    uptime = datetime.now() - boot_time
    
    days = uptime.days
    hours, remainder = divmod(uptime.seconds, 3600)
    minutes, seconds = divmod(remainder, 60)
    
    # Format as "X일 X시간 X분 X초"
    result = ""
    if days > 0:
        result += f"{days}일 "
    if hours > 0:
        result += f"{hours}시간 "
    if minutes > 0:
        result += f"{minutes}분 "
    if seconds > 0 or result == "":
        result += f"{seconds}초"
    
    return result.strip()

def parse_uptime_to_korean(uptime_str: str) -> str:
    """Parse various uptime formats and convert to Korean format"""
    if not uptime_str:
        return "-"
    
    # If already in Korean format, return as is
    if "일" in uptime_str or "시간" in uptime_str:
        return uptime_str
    
    # Try to parse "X days, HH:MM:SS" format
    import re
    match = re.match(r'(\d+)\s*days?,\s*(\d+):(\d+):(\d+)', uptime_str)
    if match:
        days = int(match.group(1))
        hours = int(match.group(2))
        minutes = int(match.group(3))
        seconds = int(match.group(4))
        
        result = ""
        if days > 0:
            result += f"{days}일 "
        if hours > 0:
            result += f"{hours}시간 "
        if minutes > 0:
            result += f"{minutes}분 "
        if seconds > 0 or result == "":
            result += f"{seconds}초"
        
        return result.strip()
    
    # Try to parse timedelta string format
    if "day" in uptime_str:
        parts = uptime_str.split(",")
        days = 0
        time_part = uptime_str
        
        if len(parts) > 1:
            days_part = parts[0].strip()
            time_part = parts[1].strip()
            days_match = re.search(r'(\d+)', days_part)
            if days_match:
                days = int(days_match.group(1))
        
        time_match = re.match(r'(\d+):(\d+):(\d+)', time_part)
        if time_match:
            hours = int(time_match.group(1))
            minutes = int(time_match.group(2))
            seconds = int(time_match.group(3))
            
            result = ""
            if days > 0:
                result += f"{days}일 "
            if hours > 0:
                result += f"{hours}시간 "
            if minutes > 0:
                result += f"{minutes}분 "
            if seconds > 0 or result == "":
                result += f"{seconds}초"
            
            return result.strip()
    
    # If parsing fails, return original
    return uptime_str

def get_os_info() -> str:
    """Get detailed OS information"""
    try:
        # Try to get detailed info
        system = platform.system()
        
        if system == "Linux":
            try:
                # Try to read from /etc/os-release
                with open("/etc/os-release", "r") as f:
                    lines = f.readlines()
                    for line in lines:
                        if line.startswith("PRETTY_NAME="):
                            return line.split("=")[1].strip().strip('"')
            except:
                pass
            
            # Check if it's DietPi
            if os.path.exists("/boot/dietpi"):
                return "DietPi"
                
        # Fallback to generic info
        return f"{platform.system()} {platform.release()}"
    except:
        return "Unknown OS"

def get_top_processes(limit: int = 5) -> List[ServerProcessInfo]:
    """Get top processes by CPU usage"""
    processes = []
    
    for proc in psutil.process_iter(['pid', 'name', 'cpu_percent', 'memory_info']):
        try:
            pinfo = proc.info
            if pinfo['cpu_percent'] > 0:
                processes.append(ServerProcessInfo(
                    name=pinfo['name'],
                    cpu_percent=round(pinfo['cpu_percent'], 2),
                    memory_mb=round(pinfo['memory_info'].rss / (1024 ** 2), 2)
                ))
        except (psutil.NoSuchProcess, psutil.AccessDenied):
            continue
    
    # Sort by CPU usage and return top N
    processes.sort(key=lambda x: x.cpu_percent, reverse=True)
    return processes[:limit]

def get_main_disk_usage():
    """Get usage of main disk partition"""
    try:
        # Get root partition usage
        usage = psutil.disk_usage('/')
        return ServerDiskInfo(
            percent=usage.percent,
            total_gb=format_bytes(usage.total),
            used_gb=format_bytes(usage.used)
        )
    except:
        return ServerDiskInfo(percent=0.0, total_gb=0.0, used_gb=0.0)

# API Endpoints
@app.get("/", tags=["General"])
async def root():
    """Root endpoint with API information"""
    return {
        "message": "System Monitoring API",
        "version": "1.0.0",
        "endpoints": {
            "system": "/system",
            "cpu": "/cpu",
            "memory": "/memory",
            "disk": "/disk",
            "network": "/network",
            "processes": "/processes",
            "all": "/all",
            "health": "/health",
            "statistics": {
                "main": "/statistics/",
                "servers": "/statistics/servers",
                "uploads_by_date": "/statistics/uploads-by-date",
                "storage": "/statistics/storage"
            }
        }
    }

@app.get("/health", tags=["General"])
async def health_check():
    """Health check endpoint"""
    return {
        "status": "healthy",
        "timestamp": datetime.now().isoformat(),
        "uptime": get_uptime_string()
    }

@app.get("/system", response_model=SystemInfo, tags=["System"])
async def get_system_info():
    """Get general system information"""
    try:
        return SystemInfo(
            hostname=socket.gethostname(),
            ip_address=get_ip_address(),
            platform=platform.system(),
            platform_release=platform.release(),
            platform_version=platform.version(),
            architecture=platform.machine(),
            processor=platform.processor() or "Unknown",
            python_version=platform.python_version(),
            boot_time=datetime.fromtimestamp(psutil.boot_time()).isoformat(),
            uptime=get_uptime_string(),
            current_time=datetime.now().isoformat()
        )
    except Exception as e:
        logger.error(f"Error getting system info: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/cpu", response_model=CPUInfo, tags=["System"])
async def get_cpu_info():
    """Get CPU information and usage"""
    try:
        cpu_freq = psutil.cpu_freq()
        
        return CPUInfo(
            count=psutil.cpu_count(logical=False) or 0,
            count_logical=psutil.cpu_count(logical=True) or 0,
            percent=psutil.cpu_percent(interval=1),
            percent_per_cpu=psutil.cpu_percent(interval=1, percpu=True),
            freq_current=round(cpu_freq.current, 2) if cpu_freq else None,
            freq_min=round(cpu_freq.min, 2) if cpu_freq else None,
            freq_max=round(cpu_freq.max, 2) if cpu_freq else None
        )
    except Exception as e:
        logger.error(f"Error getting CPU info: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/memory", response_model=MemoryInfo, tags=["System"])
async def get_memory_info():
    """Get memory information and usage"""
    try:
        mem = psutil.virtual_memory()
        
        return MemoryInfo(
            total=mem.total,
            available=mem.available,
            used=mem.used,
            free=mem.free,
            percent=mem.percent,
            total_gb=format_bytes(mem.total),
            used_gb=format_bytes(mem.used),
            available_gb=format_bytes(mem.available),
            free_gb=format_bytes(mem.free)
        )
    except Exception as e:
        logger.error(f"Error getting memory info: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/disk", response_model=List[DiskInfo], tags=["System"])
async def get_disk_info():
    """Get disk usage information for all mounted partitions"""
    try:
        disk_info = []
        
        for partition in psutil.disk_partitions():
            try:
                usage = psutil.disk_usage(partition.mountpoint)
                disk_info.append(DiskInfo(
                    device=partition.device,
                    mountpoint=partition.mountpoint,
                    fstype=partition.fstype,
                    total=usage.total,
                    used=usage.used,
                    free=usage.free,
                    percent=usage.percent,
                    total_gb=format_bytes(usage.total),
                    used_gb=format_bytes(usage.used),
                    free_gb=format_bytes(usage.free)
                ))
            except PermissionError:
                # Skip partitions that can't be accessed
                continue
                
        return disk_info
    except Exception as e:
        logger.error(f"Error getting disk info: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/network", response_model=NetworkInfo, tags=["System"])
async def get_network_info():
    """Get network I/O statistics"""
    try:
        net_io = psutil.net_io_counters()
        
        return NetworkInfo(
            bytes_sent=net_io.bytes_sent,
            bytes_recv=net_io.bytes_recv,
            packets_sent=net_io.packets_sent,
            packets_recv=net_io.packets_recv,
            errin=net_io.errin,
            errout=net_io.errout,
            dropin=net_io.dropin,
            dropout=net_io.dropout,
            bytes_sent_mb=format_bytes(net_io.bytes_sent, "MB"),
            bytes_recv_mb=format_bytes(net_io.bytes_recv, "MB"),
            bytes_sent_gb=format_bytes(net_io.bytes_sent, "GB"),
            bytes_recv_gb=format_bytes(net_io.bytes_recv, "GB")
        )
    except Exception as e:
        logger.error(f"Error getting network info: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/processes", response_model=List[ProcessInfo], tags=["System"])
async def get_processes(
    sort_by: str = "cpu_percent",
    limit: int = 10,
    min_cpu: float = 0.0,
    min_memory: float = 0.0
):
    """Get top processes sorted by CPU or memory usage"""
    try:
        processes = []
        
        for proc in psutil.process_iter(['pid', 'name', 'cpu_percent', 'memory_percent', 'memory_info', 'status', 'create_time']):
            try:
                pinfo = proc.info
                
                # Skip if below thresholds
                if pinfo['cpu_percent'] < min_cpu and pinfo['memory_percent'] < min_memory:
                    continue
                
                processes.append(ProcessInfo(
                    pid=pinfo['pid'],
                    name=pinfo['name'],
                    cpu_percent=round(pinfo['cpu_percent'], 2),
                    memory_percent=round(pinfo['memory_percent'], 2),
                    memory_mb=round(pinfo['memory_info'].rss / (1024 ** 2), 2),
                    status=pinfo['status'],
                    create_time=datetime.fromtimestamp(pinfo['create_time']).isoformat()
                ))
            except (psutil.NoSuchProcess, psutil.AccessDenied):
                continue
        
        # Sort processes
        if sort_by == "memory_percent":
            processes.sort(key=lambda x: x.memory_percent, reverse=True)
        else:
            processes.sort(key=lambda x: x.cpu_percent, reverse=True)
        
        return processes[:limit]
    except Exception as e:
        logger.error(f"Error getting process info: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/all", tags=["System"])
async def get_all_stats():
    """Get all system statistics in one call"""
    try:
        return {
            "system": await get_system_info(),
            "cpu": await get_cpu_info(),
            "memory": await get_memory_info(),
            "disk": await get_disk_info(),
            "network": await get_network_info(),
            "top_processes": await get_processes(limit=5)
        }
    except Exception as e:
        logger.error(f"Error getting all stats: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/temperature", tags=["System"])
async def get_temperature():
    """Get system temperature sensors (if available)"""
    try:
        temps = psutil.sensors_temperatures()
        if not temps:
            return {"message": "Temperature sensors not available on this system"}
        
        result = {}
        for name, entries in temps.items():
            result[name] = []
            for entry in entries:
                result[name].append({
                    "label": entry.label or "Unknown",
                    "current": round(entry.current, 2),
                    "high": round(entry.high, 2) if entry.high else None,
                    "critical": round(entry.critical, 2) if entry.critical else None
                })
        
        return result
    except AttributeError:
        return {"message": "Temperature monitoring not supported on this platform"}
    except Exception as e:
        logger.error(f"Error getting temperature info: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

# Statistics endpoints for WordPress integration
@app.get("/statistics/", tags=["Statistics"])
async def get_statistics(
    sosok: Optional[str] = None,
    site: Optional[str] = None
):
    """Get document statistics"""
    try:
        # For demo purposes, return mock data
        # In production, this would query your actual database
        stats = MOCK_STATISTICS.copy()
        
        # Add some recent uploads with current timestamps
        now = datetime.now()
        for i in range(10):
            upload_date = now - timedelta(days=i)
            stats["recent_uploads"].append({
                "filename": f"문서_{i+1}.pdf",
                "sosok": "관리자" if i % 3 == 0 else "개발팀",
                "site": "본사" if i % 2 == 0 else "지사1",
                "upload_date": upload_date.strftime("%Y%m%d"),
                "tags": "보고서,기획안" if i % 2 == 0 else "회의록"
            })
        
        return stats
    except Exception as e:
        logger.error(f"Error getting statistics: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/statistics/uploads-by-date", tags=["Statistics"])
async def get_uploads_by_date(
    days: int = 30,
    sosok: Optional[str] = None,
    site: Optional[str] = None
):
    """Get upload statistics by date"""
    try:
        # Generate mock data for the requested period
        dates = []
        counts = []
        total = 0
        
        now = datetime.now()
        for i in range(days):
            date = now - timedelta(days=i)
            dates.insert(0, date.strftime("%Y%m%d"))
            # Random count between 5 and 50
            count = 20 + (i % 10) * 3
            counts.insert(0, count)
            total += count
        
        return {
            "dates": dates,
            "counts": counts,
            "total": total
        }
    except Exception as e:
        logger.error(f"Error getting uploads by date: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/statistics/storage", tags=["Statistics"])
async def get_storage_statistics(
    sosok: Optional[str] = None,
    site: Optional[str] = None
):
    """Get storage statistics"""
    try:
        # Check if user is admin
        is_admin = (sosok == "관리자" and site == "관리자")
        
        if not is_admin:
            return {
                "access_level": "restricted",
                "message": "저장소 통계는 관리자만 볼 수 있습니다."
            }
        
        # Return mock storage data
        return {
            "total_size": 107374182400,  # 100 GB in bytes
            "total_size_gb": 100.0,
            "file_count": 15234,
            "average_file_size": 7048576,  # 7 MB average
            "size_by_type_mb": {
                "pdf": 45678.5,
                "hwp": 23456.7,
                "docx": 12345.8,
                "pptx": 8901.2,
                "txt": 3456.9,
                "others": 6160.9
            }
        }
    except Exception as e:
        logger.error(f"Error getting storage statistics: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/statistics/servers", response_model=ServerStatisticsResponse, tags=["Statistics"])
async def get_server_statistics(
    sosok: Optional[str] = None,
    site: Optional[str] = None
):
    """Get server statistics information for WordPress dashboard"""
    try:
        # Check if user is admin (based on sosok and site parameters)
        is_admin = (sosok == "관리자" and site == "관리자")
        
        if not is_admin:
            # Non-admin users get restricted access
            return ServerStatisticsResponse(
                access_level="restricted",
                message="서버 통계는 관리자만 볼 수 있습니다."
            )
        
        # Get current system stats
        cpu_percent = psutil.cpu_percent(interval=1)
        cpu_freq = psutil.cpu_freq()
        memory = psutil.virtual_memory()
        disk = get_main_disk_usage()
        network = psutil.net_io_counters()
        
        # Create Web Server info with full system details
        web_server = WebServerInfo(
            name="WEB Server",
            status="online",
            hostname=socket.gethostname(),
            ip_address=get_ip_address(),
            os_info=get_os_info(),
            platform=f"{platform.system()} {platform.release()}",
            uptime=get_uptime_string(),
            cpu=ServerCPUInfo(
                percent=cpu_percent,
                count=psutil.cpu_count(logical=False) or psutil.cpu_count(),
                freq_current=round(cpu_freq.current, 2) if cpu_freq else None
            ),
            memory=ServerMemoryInfo(
                percent=memory.percent,
                total_gb=format_bytes(memory.total),
                used_gb=format_bytes(memory.used)
            ),
            disk=disk,
            network=ServerNetworkInfo(
                bytes_sent_mb=format_bytes(network.bytes_sent, "MB"),
                bytes_recv_mb=format_bytes(network.bytes_recv, "MB")
            ),
            top_processes=get_top_processes(5)
        )
        
        # Mock AI Server info (in production, this would come from actual AI server)
        # Updated to use Korean uptime format
        boot_time_mock = datetime.now() - timedelta(days=10, hours=5, minutes=30, seconds=45)
        uptime_mock = datetime.now() - boot_time_mock
        days = uptime_mock.days
        hours, remainder = divmod(uptime_mock.seconds, 3600)
        minutes, seconds = divmod(remainder, 60)
        
        # Format as "X일 X시간 X분 X초"
        ai_uptime_str = ""
        if days > 0:
            ai_uptime_str += f"{days}일 "
        if hours > 0:
            ai_uptime_str += f"{hours}시간 "
        if minutes > 0:
            ai_uptime_str += f"{minutes}분 "
        if seconds > 0 or ai_uptime_str == "":
            ai_uptime_str += f"{seconds}초"
        ai_uptime_str = ai_uptime_str.strip()
        
        ai_server = AIServerInfo(
            name="AI Server",
            status="online",
            hostname="ai-server",
            ip_address="192.168.1.101",
            platform="Linux",
            python_version="3.11.5",
            uptime=ai_uptime_str,
            cpu=ServerCPUInfo(
                percent=35.5,
                count=8,
                freq_current=2400.0
            ),
            memory=ServerMemoryInfo(
                percent=62.3,
                total_gb=32.0,
                used_gb=19.9
            ),
            disk=ServerDiskInfo(
                percent=45.8,
                total_gb=500.0,
                used_gb=229.0
            ),
            network=ServerNetworkInfo(
                bytes_sent_mb=1024.5,
                bytes_recv_mb=2048.7
            ),
            gpu={
                "available": True,
                "count": 1,
                "devices": [{
                    "index": 0,
                    "name": "NVIDIA GeForce RTX 3090",
                    "utilization": 85,
                    "memory": {
                        "total": 24576,
                        "used": 18432,
                        "free": 6144,
                        "percent": 75.0
                    },
                    "temperature": 72,
                    "power": {
                        "draw": 320.5,
                        "limit": 350.0
                    }
                }]
            },
            top_processes=[
                ServerProcessInfo(name="python", cpu_percent=25.5, memory_mb=2048),
                ServerProcessInfo(name="uvicorn", cpu_percent=8.2, memory_mb=512),
                ServerProcessInfo(name="chromadb", cpu_percent=5.1, memory_mb=1024)
            ]
        )
        
        # Mock Vector Store info
        vector_store = VectorStoreInfo(
            name="Vector Store",
            status="online",
            type="ChromaDB",
            unique_documents=15234,
            total_vectors=45678,
            collection="documents"
        )
        
        # Return full server information
        return ServerStatisticsResponse(
            ai_server=ai_server,
            web_server=web_server,
            vector_store=vector_store
        )
        
    except Exception as e:
        logger.error(f"Error getting server statistics: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

@app.get("/all", tags=["System"], summary="Get all system statistics")
async def get_all_system_stats():
    """Get all system statistics including uptime in Korean format"""
    try:
        # Get all system info
        system_info = await get_system_info()
        cpu_info = await get_cpu_info()
        memory_info = await get_memory_info()
        disk_info = await get_disk_info()
        network_info = await get_network_info()
        top_processes = await get_processes(limit=5)
        
        # Convert uptime in system_info if it exists
        if hasattr(system_info, 'uptime') and system_info.uptime:
            system_info.uptime = parse_uptime_to_korean(system_info.uptime)
        
        return {
            "system": system_info,
            "cpu": cpu_info,
            "memory": memory_info,
            "disk": disk_info,
            "network": network_info,
            "top_processes": top_processes
        }
    except Exception as e:
        logger.error(f"Error getting all stats: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8002)